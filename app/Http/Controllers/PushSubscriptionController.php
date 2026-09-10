<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Services\Push\PushNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dar de alta y de baja el móvil de alguien para los avisos.
 *
 * Lo llama el navegador por detrás cuando se toca el interruptor. Devuelve
 * JSON porque no hay pantalla que recargar.
 */
class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        abort_unless(PushNotifier::configured(), 404);

        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:1000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ]);

        /*
         * Por la huella y no por el usuario: el navegador puede devolver el
         * mismo endpoint tras renovar la suscripción, y también puede haber
         * cambiado de cuenta en el mismo móvil. Manda el endpoint, que es lo
         * que identifica al navegador.
         */
        PushSubscription::updateOrCreate(
            ['endpoint_hash' => PushSubscription::hash($data['endpoint'])],
            [
                'user_id' => $request->user()->id,
                'endpoint' => $data['endpoint'],
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
            ],
        );

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:1000'],
        ]);

        // Sólo las propias: el endpoint lo manda el navegador y no vale como
        // permiso para borrar la de otra persona.
        PushSubscription::where('user_id', $request->user()->id)
            ->where('endpoint_hash', PushSubscription::hash($data['endpoint']))
            ->delete();

        return response()->json(['ok' => true]);
    }
}
