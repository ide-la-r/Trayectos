<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Group;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Nadie ve el libro de cuentas de un grupo al que no pertenece. Se comprueba
 * aquí y no en cada controlador para que no se olvide en ninguna ruta nueva.
 */
class EnsureGroupMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $group = $request->route('group');

        if (! $group instanceof Group) {
            abort(404);
        }

        $member = $request->user()?->memberIn($group);

        if (! $member || ! $member->active) {
            abort(403, 'No perteneces a este grupo.');
        }

        // Disponible para controladores y vistas sin volver a consultarlo
        $request->attributes->set('group_member', $member);

        return $next($request);
    }
}
