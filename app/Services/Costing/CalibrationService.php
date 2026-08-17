<?php

declare(strict_types=1);

namespace App\Services\Costing;

use App\Models\Trip;
use App\Models\Vehicle;

/**
 * Ajusta el modelo a la realidad de cada coche.
 *
 * Cualquier modelo físico con eficiencias fijas se equivoca. En vez de discutir
 * si "tu coche no gasta eso", el sistema compara lo que el propietario ha
 * repostado de verdad con lo que el modelo predijo para los viajes de ese mismo
 * periodo, y corrige. Con eso el número deja de ser opinable.
 *
 * Los viajes ya asentados NO se recalculan: su coste quedó congelado en el
 * snapshot. La calibración sólo afecta a los viajes futuros.
 */
final class CalibrationService
{
    /** @return array{factor: float, refuels: int, predicted: float, actual: float}|null */
    public function recalculate(Vehicle $vehicle): ?array
    {
        $config = config('trayectos.calibration');
        $since = now()->subDays((int) $config['lookback_days'])->toDateString();

        $refuels = $vehicle->refuels()
            ->where('refuelled_on', '>=', $since)
            ->where('full_tank', true)
            ->get();

        if ($refuels->count() < (int) $config['min_refuels']) {
            return null;
        }

        $actual = (float) $refuels->sum(fn ($refuel) => (float) ($refuel->litres ?? $refuel->kwh ?? 0));

        if ($actual <= 0) {
            return null;
        }

        // Predicción del modelo para los viajes del mismo periodo, leída del
        // snapshot: es lo que el sistema dijo entonces, no lo que diría ahora.
        $predicted = Trip::where('vehicle_id', $vehicle->id)
            ->where('travelled_on', '>=', $since)
            ->get()
            ->sum(function (Trip $trip) {
                return (float) data_get($trip->cost_inputs, 'breakdown.litres', 0)
                    + (float) data_get($trip->cost_inputs, 'breakdown.kwh', 0);
            });

        if ($predicted <= 0) {
            return null;
        }

        $factor = round(
            max((float) $config['min_factor'], min((float) $config['max_factor'], $actual / $predicted)),
            3
        );

        $vehicle->forceFill(['calibration_factor' => $factor])->save();

        return [
            'factor' => $factor,
            'refuels' => $refuels->count(),
            'predicted' => round($predicted, 2),
            'actual' => round($actual, 2),
        ];
    }
}
