<?php

declare(strict_types=1);

namespace App\Services\Ledger;

use App\Enums\EntryKind;
use App\Exceptions\UnbalancedEntryException;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\JournalEntry;
use App\Models\Settlement;
use App\Models\Trip;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Única puerta de entrada al libro mayor. Nada escribe en journal_lines sin
 * pasar por aquí, y aquí no sale ningún asiento cuyas líneas no sumen cero.
 */
final class LedgerService
{
    public function __construct(
        private readonly MoneySplitter $splitter,
        private readonly JourneyShares $journey,
    ) {}

    /**
     * Asienta un trayecto. Es idempotente: si el viaje ya tiene asiento, lo
     * devuelve sin duplicar nada (un doble submit desde una PWA con mala
     * cobertura es el caso normal, no el excepcional).
     */
    public function postTrip(Trip $trip, ?int $createdBy = null): ?JournalEntry
    {
        return DB::transaction(function () use ($trip, $createdBy) {
            $existing = JournalEntry::where('group_id', $trip->group_id)
                ->where('external_ref', "trip:{$trip->id}")
                ->first();

            if ($existing) {
                return $existing;
            }

            $shares = $this->tripShares($trip);
            $driverId = $trip->driver_member_id;

            $lines = [];
            $creditToDriver = 0;

            foreach ($shares as $memberId => $cents) {
                if ((int) $memberId === (int) $driverId || $cents === 0) {
                    continue;   // la parte del conductor se netea contra su crédito
                }

                $lines[] = [
                    'group_member_id' => (int) $memberId,
                    'amount_cents' => -$cents,
                    'memo' => 'Parte del trayecto',
                ];

                $creditToDriver += $cents;
            }

            if ($creditToDriver === 0) {
                return null;    // viajó solo (o nadie más asume coste): nada que repartir
            }

            $lines[] = [
                'group_member_id' => $driverId,
                'amount_cents' => $creditToDriver,
                'memo' => 'Coste adelantado del trayecto',
            ];

            $trip->loadMissing('group');

            $entry = $this->createEntry(
                group: $trip->group,
                kind: EntryKind::Trip,
                description: "{$trip->origin_label} → {$trip->destination_label}",
                occurredOn: $trip->travelled_on,
                lines: $lines,
                externalRef: "trip:{$trip->id}",
                createdBy: $createdBy ?? $trip->created_by,
            );

            $trip->forceFill(['journal_entry_id' => $entry->id])->save();

            return $entry;
        });
    }

    /**
     * Reparto del coste del trayecto entre quienes lo asumen.
     *
     * @return array<int, int> [group_member_id => céntimos]
     */
    public function tripShares(Trip $trip): array
    {
        $group = $trip->group;
        $passengers = $trip->passengers()->get();

        $weights = [];

        foreach ($passengers as $passenger) {
            $isDriver = (int) $passenger->group_member_id === (int) $trip->driver_member_id;

            if ($isDriver && ! $group->driver_pays_own_share) {
                continue;
            }

            $weights[$passenger->group_member_id] = (float) $passenger->weight;
        }

        /*
         * Tramo a tramo, no proporcional al peso: el trozo de viaje que hizo
         * uno solo lo paga él entero. Mira JourneyShares.
         */
        $shares = $this->journey->fractions($weights);

        /*
         * Lo que se cobra puede ser menos que el viaje entero: si el conductor
         * no paga su parte, los tramos en los que iba solo no son de nadie. Se
         * reparte ESO y no el total, o el sobrante acabaría cayendo sobre los
         * pasajeros, que es justo lo que no queremos.
         */
        $charged = (int) round($trip->total_cost_cents * array_sum($shares));

        return $this->splitter->split($charged, $shares, seed: (int) $trip->id);
    }

    /** Registra un pago real entre dos miembros y su asiento correspondiente. */
    public function postSettlement(
        GroupMember $from,
        GroupMember $to,
        int $amountCents,
        Carbon $settledOn,
        ?string $method = null,
        ?int $createdBy = null,
    ): Settlement {
        if ($amountCents <= 0) {
            throw new \InvalidArgumentException('El importe de una liquidación debe ser positivo.');
        }

        if ($from->id === $to->id) {
            throw new \InvalidArgumentException('Una liquidación necesita dos personas distintas.');
        }

        if ($from->group_id !== $to->group_id) {
            throw new \InvalidArgumentException('Ambos miembros deben pertenecer al mismo grupo.');
        }

        return DB::transaction(function () use ($from, $to, $amountCents, $settledOn, $method, $createdBy) {
            $from->loadMissing(['group', 'user']);
            $to->loadMissing('user');

            // Quien paga reduce su deuda (+), quien cobra reduce su crédito (−)
            $entry = $this->createEntry(
                group: $from->group,
                kind: EntryKind::Settlement,
                description: "Pago de {$from->name()} a {$to->name()}",
                occurredOn: $settledOn,
                lines: [
                    ['group_member_id' => $from->id, 'amount_cents' => $amountCents, 'memo' => 'Pago realizado'],
                    ['group_member_id' => $to->id, 'amount_cents' => -$amountCents, 'memo' => 'Pago recibido'],
                ],
                createdBy: $createdBy,
            );

            return Settlement::create([
                'group_id' => $from->group_id,
                'from_member_id' => $from->id,
                'to_member_id' => $to->id,
                'amount_cents' => $amountCents,
                'method' => $method,
                'settled_on' => $settledOn->toDateString(),
                'journal_entry_id' => $entry->id,
                'created_by' => $createdBy,
            ]);
        });
    }

    /**
     * Anula un asiento con su contrario. El original nunca se toca: el libro
     * es append-only y la anulación queda visible en el histórico.
     */
    public function reverse(JournalEntry $entry, string $reason, ?int $createdBy = null): JournalEntry
    {
        return DB::transaction(function () use ($entry, $reason, $createdBy) {
            $existing = JournalEntry::where('reverses_id', $entry->id)->first();

            if ($existing) {
                return $existing;
            }

            $entry->loadMissing(['lines', 'group']);

            $lines = $entry->lines->map(fn ($line) => [
                'group_member_id' => $line->group_member_id,
                'amount_cents' => -$line->amount_cents,
                'memo' => 'Anulación',
            ])->all();

            return $this->createEntry(
                group: $entry->group,
                kind: EntryKind::Reversal,
                description: "Anulación: {$reason}",
                occurredOn: now(),
                lines: $lines,
                externalRef: "reversal:{$entry->id}",
                createdBy: $createdBy,
                reversesId: $entry->id,
            );
        });
    }

    /** Ajuste manual libre (deudas antiguas, invitaciones, correcciones). */
    public function postAdjustment(
        Group $group,
        array $lines,
        string $description,
        ?Carbon $occurredOn = null,
        ?int $createdBy = null,
    ): JournalEntry {
        return $this->createEntry(
            group: $group,
            kind: EntryKind::Adjustment,
            description: $description,
            occurredOn: $occurredOn ?? now(),
            lines: $lines,
            createdBy: $createdBy,
        );
    }

    /**
     * Crea el asiento y sus líneas. Aquí vive la invariante del sistema.
     *
     * @param  array<int, array{group_member_id: int, amount_cents: int, memo?: string|null}>  $lines
     */
    public function createEntry(
        Group $group,
        EntryKind $kind,
        string $description,
        Carbon $occurredOn,
        array $lines,
        ?string $externalRef = null,
        ?int $createdBy = null,
        ?int $reversesId = null,
    ): JournalEntry {
        $lines = array_values(array_filter($lines, fn (array $line) => (int) $line['amount_cents'] !== 0));

        $total = array_sum(array_column($lines, 'amount_cents'));

        if ($total !== 0) {
            throw UnbalancedEntryException::forTotal((int) $total);
        }

        return DB::transaction(function () use ($group, $kind, $description, $occurredOn, $lines, $externalRef, $createdBy, $reversesId) {
            $entry = JournalEntry::create([
                'group_id' => $group->id,
                'kind' => $kind,
                'description' => mb_substr($description, 0, 255),
                'occurred_on' => $occurredOn->toDateString(),
                'external_ref' => $externalRef,
                'reverses_id' => $reversesId,
                'created_by' => $createdBy,
            ]);

            foreach ($lines as $line) {
                $entry->lines()->create([
                    'group_member_id' => $line['group_member_id'],
                    'amount_cents' => (int) $line['amount_cents'],
                    'memo' => $line['memo'] ?? null,
                ]);
            }

            return $entry->load('lines');
        });
    }
}
