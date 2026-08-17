<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Las tareas programadas las dispara un cron externo (GitHub Actions) porque en
 * el plan gratuito no hay worker ni scheduler propio. Este token es lo único
 * que separa ese cron del resto de internet.
 */
class VerifyInternalTaskToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('trayectos.internal_task_token');
        $provided = (string) ($request->bearerToken() ?? $request->input('token', ''));

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            abort(401, 'Token interno no válido.');
        }

        return $next($request);
    }
}
