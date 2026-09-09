<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Vehicle;
use App\Services\Costing\CalibrationService;
use Illuminate\Console\Command;

class CalibrateVehicles extends Command
{
    protected $signature = 'trayectos:calibrate {--vehicle= : Id de un vehículo concreto}';

    protected $description = 'Recalcula el factor de calibración de cada vehículo con sus repostajes reales';

    public function handle(CalibrationService $calibration): int
    {
        $vehicles = Vehicle::query()
            ->when($this->option('vehicle'), fn ($query, $id) => $query->whereKey($id))
            ->where('active', true)
            ->get();

        $adjusted = 0;

        foreach ($vehicles as $vehicle) {
            $result = $calibration->recalculate($vehicle);

            if ($result['status'] === 'calibrated') {
                $adjusted++;

                $this->info(sprintf(
                    '· %s: factor %.3f — gasta %.2f por cada 100 km y el modelo decía %.2f (%d depósitos, %d km, %d%% apuntado)',
                    $vehicle->label,
                    $result['factor'],
                    $result['measured_per_100'],
                    $result['model_per_100'],
                    $result['tanks'],
                    $result['km'],
                    (int) round($result['coverage'] * 100),
                ));

                continue;
            }

            // Decir POR QUÉ no se ha ajustado: «sin datos suficientes» no le
            // dice a nadie qué le falta por apuntar.
            $this->line('· '.$vehicle->label.': '.match ($result['status']) {
                'thin_sample' => sprintf(
                    'gasta %.2f por cada 100 km, pero sólo hay %d de %d km apuntados como viajes: no hay muestra para ajustar el modelo.',
                    $result['measured_per_100'], $result['logged_km'], $result['km'],
                ),
                'no_trips' => sprintf(
                    'gasta %.2f por cada 100 km. Sin viajes apuntados en ese periodo no hay con qué comparar.',
                    $result['measured_per_100'],
                ),
                default => 'todavía no hay dos llenados completos con el cuentakilómetros apuntado.',
            });
        }

        $this->newLine();
        $this->info("{$adjusted} de {$vehicles->count()} vehículos recalibrados.");

        return self::SUCCESS;
    }
}
