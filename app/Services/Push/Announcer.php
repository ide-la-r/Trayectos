<?php

declare(strict_types=1);

namespace App\Services\Push;

use App\Models\GroupMember;
use App\Models\Trip;
use App\Support\Money;

/**
 * Qué se avisa y con qué palabras.
 *
 * Sólo se avisa de lo que mueve dinero de alguien y lo ha movido OTRA persona:
 * un viaje apuntado, uno anulado y un pago. Nada de recordatorios ni de
 * resúmenes. Una aplicación que avisa de más se acaba silenciando entera, y
 * entonces tampoco avisa de lo que importa.
 *
 * El importe va en el aviso a propósito: «Ana apuntó un viaje» obliga a abrir
 * la aplicación para saber si son dos euros o veinte.
 */
final class Announcer
{
    public function __construct(private readonly PushNotifier $push) {}

    public function tripRecorded(Trip $trip, int $byUserId): void
    {
        $this->aboutTrip(
            $trip,
            $byUserId,
            fn (string $who, string $amount) => new PushMessage(
                title: "$who apuntó {$trip->origin_label} → {$trip->destination_label}",
                body: $amount === '' ? 'Ya está repartido en el libro.' : "Te toca poner $amount.",
                url: route('trips.show', [$trip->group_id, $trip->id], absolute: false),
                tag: "trip-{$trip->id}",
            ),
        );
    }

    public function tripCancelled(Trip $trip, int $byUserId): void
    {
        $this->aboutTrip(
            $trip,
            $byUserId,
            // El importe no hace falta aquí: lo que importa es que ya no cuenta
            fn (string $who, string $amount) => new PushMessage(
                title: "$who anuló {$trip->origin_label} → {$trip->destination_label}",
                body: 'Del '.$trip->travelled_on->format('d/m/Y').'. Ya no cuenta en tu saldo.',
                url: route('trips.show', [$trip->group_id, $trip->id], absolute: false),
                tag: "trip-{$trip->id}",
            ),
        );
    }

    public function settlementRecorded(GroupMember $from, GroupMember $to, int $amountCents, int $byUserId): void
    {
        $payer = $from->user?->name ?? 'Alguien';

        $this->push->notify(
            [$to->user],
            new PushMessage(
                title: "$payer te ha pagado ".Money::format($amountCents),
                body: 'Apuntado en el libro. Tu saldo ya lo refleja.',
                url: route('settlements.index', $to->group_id, absolute: false),
                tag: "settlement-{$to->group_id}",
            ),
            exceptUserId: $byUserId,
        );
    }

    /**
     * A cada quien lo suyo: el aviso lleva lo que le toca a esa persona, así
     * que se manda uno por cabeza en vez de uno igual para todos.
     *
     * @param  callable(string, string): PushMessage  $compose
     */
    private function aboutTrip(Trip $trip, int $byUserId, callable $compose): void
    {
        $trip->loadMissing(['passengers.member.user', 'driver.user', 'journalEntry.lines']);

        $who = $trip->driver?->user?->name ?? 'Alguien';

        // Lo que se le cargó a cada uno, del asiento. Viene en negativo —le
        // debe al grupo— y en el aviso se dice en positivo.
        $owed = $trip->journalEntry?->lines
            ->where('amount_cents', '<', 0)
            ->keyBy('group_member_id')
            ?? collect();

        foreach ($trip->passengers as $passenger) {
            $user = $passenger->member?->user;

            if (! $user || $user->id === $byUserId) {
                continue;
            }

            $cents = $owed->get($passenger->group_member_id)?->amount_cents;

            $this->push->notify(
                [$user],
                $compose($who, $cents === null ? '' : Money::format(abs($cents))),
                exceptUserId: $byUserId,
            );
        }
    }
}
