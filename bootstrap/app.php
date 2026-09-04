<?php

use App\Http\Middleware\EnsureGroupMember;
use App\Http\Middleware\VerifyInternalTaskToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'group.member' => EnsureGroupMember::class,
            'internal.token' => VerifyInternalTaskToken::class,
        ]);

        // Las rutas internas las llama el cron de GitHub Actions con un POST sin
        // formulario ni sesión, así que no hay token CSRF que enviar: el grupo
        // «web» las rechazaba con un 419 antes de llegar a VerifyInternalTaskToken.
        // Lo que las protege es el bearer token de ese middleware, no el CSRF.
        $middleware->validateCsrfTokens(except: [
            'internal/*',
        ]);

        // En Render (y en cualquier túnel) la aplicación va detrás de un proxy
        // que termina el HTTPS. Sin esto Laravel generaría enlaces http:// y el
        // navegador bloquearía los assets de una página servida por https.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
