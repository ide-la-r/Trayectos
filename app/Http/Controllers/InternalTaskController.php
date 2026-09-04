<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;

/**
 * En el plan gratuito no hay scheduler ni worker permanente: las tareas
 * periódicas las dispara un cron externo (GitHub Actions) contra estos
 * endpoints, protegidos por un token compartido.
 */
class InternalTaskController extends Controller
{
    /**
     * Estas tareas corren dentro de la petición HTTP, así que heredan el
     * max_execution_time de PHP —30 s por defecto—, y descargar las estaciones
     * de una provincia del Ministerio se lo come entero: la petición moría a
     * los 30 s con un 500. Se amplía sólo aquí, no en un php.ini global: una
     * petición de una persona que tarde más de 30 s es un error, esta no.
     */
    private function allowLongTask(): void
    {
        set_time_limit(600);
    }

    public function syncPrices(): JsonResponse
    {
        $this->allowLongTask();

        $exitCode = Artisan::call('trayectos:sync-prices');

        return response()->json([
            'ok' => $exitCode === 0,
            'output' => trim(Artisan::output()),
        ], $exitCode === 0 ? 200 : 500);
    }

    public function calibrate(): JsonResponse
    {
        $this->allowLongTask();

        $exitCode = Artisan::call('trayectos:calibrate');

        return response()->json([
            'ok' => $exitCode === 0,
            'output' => trim(Artisan::output()),
        ], $exitCode === 0 ? 200 : 500);
    }
}
