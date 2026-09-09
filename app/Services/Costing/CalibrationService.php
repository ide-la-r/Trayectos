<?php

declare(strict_types=1);

namespace App\Services\Costing;

use App\Models\Trip;
use App\Models\Vehicle;
use App\Support\TankRun;
use Illuminate\Support\Collection;

/**
 * Ajusta el modelo a la realidad de cada coche.
 *
 * Cualquier modelo físico con eficiencias fijas se equivoca. En vez de discutir
 * si «tu coche no gasta eso», el sistema compara lo que el coche ha gastado de
 * verdad con lo que el modelo predijo, y corrige. Con eso el número deja de ser
 * opinable.
 *
 * Se comparan RITMOS y no totales, y ésa es la corrección importante: antes se
 * dividía todo lo repostado entre lo previsto para los viajes apuntados, así
 * que cada kilómetro que no se apuntaba —ir a trabajar, la compra, llevar a los
 * niños— entraba en el numerador y no en el denominador. El factor subía sin
 * que el coche gastara de más, y con apuntar la mitad de lo que se conduce ya
 * se clavaba en el tope de 1,350: un 35 % de más en todos los viajes futuros.
 *
 * Midiendo litros por cada cien kilómetros a los dos lados, los kilómetros sin
 * apuntar se van solos de la cuenta. Lo único que hace falta es que los viajes
 * apuntados den para una muestra, y que el cuentakilómetros esté anotado.
 *
 * Los viajes ya asentados NO se recalculan: su coste quedó congelado en el
 * snapshot. La calibración sólo afecta a los viajes futuros.
 */
final class CalibrationService
{
    public function __construct(private readonly RealConsumption $real) {}

    /**
     * @return array{
     *     status: 'calibrated'|'no_tanks'|'no_trips'|'thin_sample',
     *     tanks: int, km: int, measured_per_100: float|null,
     *     litres_per_100: float|null, kwh_per_100: float|null,
     *     factor?: float, model_per_100?: float, logged_km?: int, coverage?: float
     * }
     */
    public function recalculate(Vehicle $vehicle): array
    {
        $config = config('trayectos.calibration');
        $since = now()->subDays((int) $config['lookback_days'])->startOfDay();

        $tanks = $this->real->tanks($vehicle)
            ->filter(fn (TankRun $run) => $run->to->greaterThanOrEqualTo($since))
            ->values();

        $measured = $this->real->summarise($tanks);
        $spent = $this->spent($tanks);

        $outcome = [
            'litres_per_100' => $measured['litres'],
            'kwh_per_100' => $measured['kwh'],
            'tanks' => $measured['tanks'],
            'km' => $measured['km'],
            'measured_per_100' => $measured['km'] > 0 && $spent > 0
                ? round($spent / $measured['km'] * 100, 2)
                : null,
        ];

        if ($measured['tanks'] < (int) $config['min_tanks'] || $measured['km'] <= 0 || $spent <= 0) {
            return ['status' => 'no_tanks', ...$outcome];
        }

        /*
         * Los viajes del mismo periodo que los depósitos medidos, no de los 180
         * días enteros: comparar el gasto de marzo con los viajes de enero no
         * dice nada.
         */
        $trips = Trip::where('vehicle_id', $vehicle->id)
            ->whereBetween('travelled_on', [$tanks->first()->from->toDateString(), $tanks->last()->to->toDateString()])
            ->get();

        $loggedKm = (int) round($trips->sum('distance_m') / 1000);

        // La predicción sale del snapshot —lo que el sistema dijo entonces, no
        // lo que diría ahora— y viene SIN calibrar, que es justo lo que hay que
        // comparar con la realidad.
        $predicted = (float) $trips->sum(
            fn (Trip $trip) => (float) data_get($trip->cost_inputs, 'breakdown.litres', 0)
                + (float) data_get($trip->cost_inputs, 'breakdown.kwh', 0)
        );

        if ($loggedKm <= 0 || $predicted <= 0) {
            return ['status' => 'no_trips', ...$outcome];
        }

        $coverage = round($loggedKm / $measured['km'], 3);

        /*
         * Con cuatro kilómetros apuntados de cada mil conducidos, el ritmo del
         * modelo lo marca un único viaje y no representa nada. No se calibra,
         * pero el consumo real medido sí vale y se enseña igual.
         */
        if ($coverage < (float) $config['min_trip_coverage']) {
            return ['status' => 'thin_sample', ...$outcome, 'logged_km' => $loggedKm, 'coverage' => $coverage];
        }

        $modelPer100 = $predicted / $loggedKm * 100;

        $factor = round(
            max((float) $config['min_factor'], min((float) $config['max_factor'], $outcome['measured_per_100'] / $modelPer100)),
            3
        );

        $vehicle->forceFill(['calibration_factor' => $factor])->save();

        return [
            'status' => 'calibrated',
            ...$outcome,
            'factor' => $factor,
            'model_per_100' => round($modelPer100, 2),
            'logged_km' => $loggedKm,
            'coverage' => $coverage,
        ];
    }

    /**
     * Lo gastado en esos depósitos.
     *
     * En un híbrido enchufable se suman litros y kWh, que no son la misma
     * unidad. Es una aproximación consciente: como el mismo apaño está a los
     * dos lados de la división, en el factor se compensa.
     *
     * @param  Collection<int, TankRun>  $tanks
     */
    private function spent(Collection $tanks): float
    {
        return (float) $tanks->sum(fn (TankRun $run) => ($run->litres ?? 0) + ($run->kwh ?? 0));
    }
}
