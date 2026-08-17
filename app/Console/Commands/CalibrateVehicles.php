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

            if ($result === null) {
                $this->line("· {$vehicle->label}: sin datos suficientes todavía.");

                continue;
            }

            $adjusted++;
            $this->info(sprintf(
                '· %s: factor %.3f (%d repostajes · %.1f reales frente a %.1f previstos)',
                $vehicle->label,
                $result['factor'],
                $result['refuels'],
                $result['actual'],
                $result['predicted'],
            ));
        }

        $this->newLine();
        $this->info("{$adjusted} de {$vehicles->count()} vehículos recalibrados.");

        return self::SUCCESS;
    }
}
