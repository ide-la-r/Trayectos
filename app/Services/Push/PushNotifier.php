<?php

declare(strict_types=1);

namespace App\Services\Push;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Manda los avisos al móvil de la gente.
 *
 * Va dentro de la petición de quien apunta el viaje y no en una cola, porque
 * en el plan gratuito de Render no hay trabajador: un aviso encolado se
 * quedaría esperando al cron, que pasa cuatro veces al día, y un aviso que
 * llega seis horas tarde no es un aviso. Son cuatro peticiones en paralelo
 * para un grupo de cuatro.
 *
 * NADA de lo que pase aquí puede tumbar lo que se estaba haciendo. Apuntar un
 * viaje es lo importante y el aviso es un extra: si Apple no contesta, si
 * faltan las claves o si la librería revienta, se anota en el registro y se
 * sigue. Por eso todo está envuelto y por eso no se lanza ninguna excepción.
 */
// Sin «final» a propósito: es la única pieza que habla con Apple y Google, y
// los tests la sustituyen por un doble para no mandar avisos de verdad.
class PushNotifier
{
    /** Configurado sólo si hay claves: sin ellas la función no existe. */
    public static function configured(): bool
    {
        return filled(config('trayectos.push.public_key'))
            && filled(config('trayectos.push.private_key'));
    }

    /**
     * Avisa a estas personas, menos a quien ha provocado el aviso.
     *
     * Lo de excluir al autor no es un detalle: sin ello, quien apunta el viaje
     * recibe un aviso de su propio viaje, y eso hace que la gente apague los
     * avisos el primer día.
     *
     * @param  Collection<int, User>|array<int, User>  $users
     */
    public function notify(Collection|array $users, PushMessage $message, ?int $exceptUserId = null): int
    {
        if (! self::configured()) {
            return 0;
        }

        $userIds = collect($users)
            ->pluck('id')
            ->filter(fn (int $id) => $id !== $exceptUserId)
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            return 0;
        }

        $subscriptions = PushSubscription::whereIn('user_id', $userIds)->get();

        if ($subscriptions->isEmpty()) {
            return 0;
        }

        try {
            return $this->send($subscriptions, $message);
        } catch (Throwable $exception) {
            Log::warning('No se han podido mandar los avisos.', [
                'error' => $exception->getMessage(),
                'subscriptions' => $subscriptions->count(),
            ]);

            return 0;
        }
    }

    /**
     * @param  Collection<int, PushSubscription>  $subscriptions
     */
    private function send(Collection $subscriptions, PushMessage $message): int
    {
        $push = new WebPush(
            auth: ['VAPID' => [
                'subject' => (string) config('trayectos.push.subject'),
                'publicKey' => (string) config('trayectos.push.public_key'),
                'privateKey' => (string) config('trayectos.push.private_key'),
            ]],
            defaultOptions: ['TTL' => (int) config('trayectos.push.ttl')],
            timeout: (int) config('trayectos.push.timeout'),
        );

        $payload = $message->toJson();

        foreach ($subscriptions as $subscription) {
            $push->queueNotification(
                Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->public_key,
                    'authToken' => $subscription->auth_token,
                ]),
                $payload,
            );
        }

        $sent = 0;
        $gone = [];

        foreach ($push->flush() as $report) {
            if ($report->isSuccess()) {
                $sent++;

                continue;
            }

            /*
             * Una suscripción caducada es lo normal, no un fallo: pasa al
             * desinstalar la aplicación o al limpiar los datos del navegador.
             * Se borra, porque si no se reintenta para siempre.
             */
            if ($report->isSubscriptionExpired()) {
                $gone[] = PushSubscription::hash($report->getEndpoint());

                continue;
            }

            Log::warning('Un aviso no ha llegado.', ['motivo' => $report->getReason()]);
        }

        if ($gone !== []) {
            PushSubscription::whereIn('endpoint_hash', $gone)->delete();
        }

        return $sent;
    }
}
