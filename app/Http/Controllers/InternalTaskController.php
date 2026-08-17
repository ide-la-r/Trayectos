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
    public function syncPrices(): JsonResponse
    {
        $exitCode = Artisan::call('trayectos:sync-prices');

        return response()->json([
            'ok' => $exitCode === 0,
            'output' => trim(Artisan::output()),
        ], $exitCode === 0 ? 200 : 500);
    }

    public function calibrate(): JsonResponse
    {
        $exitCode = Artisan::call('trayectos:calibrate');

        return response()->json([
            'ok' => $exitCode === 0,
            'output' => trim(Artisan::output()),
        ], $exitCode === 0 ? 200 : 500);
    }
}
