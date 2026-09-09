<?php

declare(strict_types=1);

namespace App\Services\Costing;

use App\Models\Refuel;
use App\Models\Vehicle;
use App\Support\TankRun;
use Illuminate\Support\Collection;

/**
 * El consumo de verdad de un coche, medido de llenado a llenado.
 *
 * El cuentakilómetros se venía pidiendo en el formulario de repostaje desde el
 * principio y no se usaba para nada. Es el dato que convierte una lista de
 * gastos en una medida: con él, dos llenados completos dicen exactamente lo
 * que gasta este coche, sin modelo físico de por medio y sin depender de que
 * los viajes estén apuntados.
 */
final class RealConsumption
{
    /**
     * Los depósitos completos que se pueden medir, del más antiguo al último.
     *
     * @return Collection<int, TankRun>
     */
    public function tanks(Vehicle $vehicle): Collection
    {
        $limits = config('trayectos.real_consumption');

        $refuels = $vehicle->refuels()
            ->whereNotNull('odometer_km')
            ->orderBy('odometer_km')
            ->orderBy('refuelled_on')
            ->get();

        $runs = collect();
        $anchor = null;
        $litres = 0.0;
        $kwh = 0.0;

        foreach ($refuels as $refuel) {
            // Todo lo repostado desde el último lleno entró en este depósito,
            // incluidos los repostajes parciales de en medio.
            $litres += (float) ($refuel->litres ?? 0);
            $kwh += (float) ($refuel->kwh ?? 0);

            if (! $refuel->full_tank) {
                continue;
            }

            if ($anchor instanceof Refuel) {
                $run = new TankRun(
                    refuelId: (int) $refuel->id,
                    from: $anchor->refuelled_on,
                    to: $refuel->refuelled_on,
                    km: (int) $refuel->odometer_km - (int) $anchor->odometer_km,
                    litres: $litres > 0 ? round($litres, 2) : null,
                    kwh: $kwh > 0 ? round($kwh, 2) : null,
                );

                if ($this->plausible($run, $limits)) {
                    $runs->push($run);
                }
            }

            $anchor = $refuel;
            $litres = 0.0;
            $kwh = 0.0;
        }

        return $runs;
    }

    /**
     * Lo que gasta este coche de verdad cada cien kilómetros.
     *
     * Se pesa por kilómetros y no por depósitos: un depósito de mil kilómetros
     * dice más de cómo gasta el coche que uno de doscientos, y promediar los
     * dos a partes iguales le daría la misma voz a los dos.
     *
     * @return array{litres: float|null, kwh: float|null, km: int, tanks: int}
     */
    public function average(Vehicle $vehicle): array
    {
        $tanks = $this->tanks($vehicle);

        return $this->summarise($tanks);
    }

    /**
     * @param  Collection<int, TankRun>  $tanks
     * @return array{litres: float|null, kwh: float|null, km: int, tanks: int}
     */
    public function summarise(Collection $tanks): array
    {
        $km = (int) $tanks->sum('km');
        $litres = (float) $tanks->sum(fn (TankRun $run) => $run->litres ?? 0);
        $kwh = (float) $tanks->sum(fn (TankRun $run) => $run->kwh ?? 0);

        return [
            'litres' => $km > 0 && $litres > 0 ? round($litres / $km * 100, 2) : null,
            'kwh' => $km > 0 && $kwh > 0 ? round($kwh / $km * 100, 2) : null,
            'km' => $km,
            'tanks' => $tanks->count(),
        ];
    }

    /**
     * Un depósito que no puede ser.
     *
     * Casi siempre es un cuentakilómetros mal tecleado: un cero de más
     * convierte un depósito normal en cuarenta mil kilómetros con cincuenta
     * litros, y ese dato entra en la media y la destroza. Se descarta el
     * depósito, no el repostaje: el gasto sigue apuntado.
     */
    private function plausible(TankRun $run, array $limits): bool
    {
        if ($run->km < (int) $limits['min_tank_km'] || $run->km > (int) $limits['max_tank_km']) {
            return false;
        }

        foreach ([[$run->litresPer100(), 'litres'], [$run->kwhPer100(), 'kwh']] as [$per100, $unit]) {
            if ($per100 === null) {
                continue;
            }

            if ($per100 < (float) $limits["min_{$unit}_per_100"] || $per100 > (float) $limits["max_{$unit}_per_100"]) {
                return false;
            }
        }

        return true;
    }
}
