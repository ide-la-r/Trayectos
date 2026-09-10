<?php

namespace Tests\Feature;

use App\Enums\EntryKind;
use App\Exceptions\UnbalancedEntryException;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Ledger\BalanceService;
use App\Services\Ledger\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerTest extends TestCase
{
    use RefreshDatabase;

    private Group $group;

    /** @var array<string, GroupMember> */
    private array $members = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->group = Group::factory()->create(['name' => 'Los del jueves']);

        foreach (['ana', 'bea', 'carlos', 'diego'] as $name) {
            $this->members[$name] = GroupMember::factory()->create([
                'group_id' => $this->group->id,
                'user_id' => User::factory()->create(['name' => ucfirst($name)])->id,
            ]);
        }
    }

    private function ledger(): LedgerService
    {
        return app(LedgerService::class);
    }

    private function trip(int $costCents, array $passengerNames, string $driver = 'ana'): Trip
    {
        $vehicle = Vehicle::factory()->create([
            'owner_id' => $this->members[$driver]->user_id,
        ]);

        $trip = Trip::create([
            'group_id' => $this->group->id,
            'vehicle_id' => $vehicle->id,
            'driver_member_id' => $this->members[$driver]->id,
            'travelled_on' => now()->toDateString(),
            'origin_label' => 'Madrid',
            'destination_label' => 'Navacerrada',
            'distance_m' => 60_000,
            'ascent_m' => 900,
            'descent_m' => 120,
            'total_cost_cents' => $costCents,
            'cost_inputs' => ['inputs' => [], 'breakdown' => []],
            'formula_version' => 3,
        ]);

        foreach ($passengerNames as $name) {
            $trip->passengers()->create([
                'group_member_id' => $this->members[$name]->id,
                'weight' => 1.0,
            ]);
        }

        return $trip;
    }

    public function test_a_trip_credits_the_driver_and_debits_the_passengers(): void
    {
        $trip = $this->trip(2400, ['ana', 'bea', 'carlos', 'diego']);

        $entry = $this->ledger()->postTrip($trip);

        $this->assertNotNull($entry);
        $this->assertSame(EntryKind::Trip, $entry->kind);
        $this->assertSame(0, $entry->balanceCents());

        $balances = app(BalanceService::class);

        // 24 € entre cuatro: la conductora recupera las tres partes ajenas
        $this->assertSame(1800, $balances->forMember($this->members['ana']));
        $this->assertSame(-600, $balances->forMember($this->members['bea']));
        $this->assertSame(-600, $balances->forMember($this->members['carlos']));
        $this->assertSame(-600, $balances->forMember($this->members['diego']));
    }

    public function test_group_balances_always_add_up_to_zero(): void
    {
        // Importe que no se divide en partes exactas: 25,00 € entre 3
        $this->ledger()->postTrip($this->trip(2500, ['ana', 'bea', 'carlos']));

        $this->assertTrue(app(BalanceService::class)->isConsistent($this->group));
    }

    public function test_the_driver_can_ride_for_free_when_the_group_says_so(): void
    {
        $this->group->update(['driver_pays_own_share' => false]);

        $trip = $this->trip(3000, ['ana', 'bea', 'carlos']);
        $this->ledger()->postTrip($trip);

        $balances = app(BalanceService::class);

        // El coste se reparte sólo entre los dos pasajeros
        $this->assertSame(3000, $balances->forMember($this->members['ana']));
        $this->assertSame(-1500, $balances->forMember($this->members['bea']));
        $this->assertSame(-1500, $balances->forMember($this->members['carlos']));
    }

    /** Como trip(), pero diciendo qué parte del viaje hizo cada uno. */
    private function tripByLeg(int $costCents, array $weightsByName, string $driver = 'ana'): Trip
    {
        $trip = $this->trip($costCents, [], $driver);

        foreach ($weightsByName as $name => $weight) {
            $trip->passengers()->create([
                'group_member_id' => $this->members[$name]->id,
                'weight' => $weight,
            ]);
        }

        return $trip;
    }

    public function test_the_leg_someone_rode_alone_is_theirs_alone(): void
    {
        /*
         * Ana conduce el viaje entero y recoge a Bea a mitad de camino. La
         * primera mitad la hizo Ana sola y es suya entera; la segunda la
         * pagan a medias:
         *
         *   3.000 → Ana 2.250 · Bea 750
         *
         * El reparto proporcional de antes daba 2.000 y 1.000: le cobraba de
         * más a quien menos viaje había hecho.
         */
        $trip = $this->tripByLeg(3000, ['ana' => 1.0, 'bea' => 0.5]);
        $this->ledger()->postTrip($trip);

        $balances = app(BalanceService::class);

        // Ana pone los 3.000 y le tocan 2.250: le deben 750
        $this->assertSame(750, $balances->forMember($this->members['ana']));
        $this->assertSame(-750, $balances->forMember($this->members['bea']));
    }

    public function test_a_passenger_who_rode_half_pays_half_when_the_driver_rides_free(): void
    {
        /*
         * Con el conductor sin pagar su parte, un pasajero que va todo el
         * viaje paga el viaje entero. Si sólo va la mitad, paga la mitad: el
         * tramo en el que Ana iba sola no es de nadie y se lo come ella.
         *
         * Antes Bea pagaba los 3.000 completos por haber hecho la mitad.
         */
        $this->group->update(['driver_pays_own_share' => false]);

        $trip = $this->tripByLeg(3000, ['ana' => 1.0, 'bea' => 0.5]);
        $this->ledger()->postTrip($trip);

        $balances = app(BalanceService::class);

        $this->assertSame(1500, $balances->forMember($this->members['ana']));
        $this->assertSame(-1500, $balances->forMember($this->members['bea']));
    }

    public function test_an_entry_with_legs_still_adds_up_to_zero(): void
    {
        // La regla que no se puede romper nunca, haya tramos o no
        $trip = $this->tripByLeg(1237, ['ana' => 1.0, 'bea' => 0.5, 'carlos' => 0.25]);

        $entry = $this->ledger()->postTrip($trip);

        $this->assertSame(0, $entry->balanceCents());
    }

    public function test_posting_the_same_trip_twice_does_not_duplicate_the_entry(): void
    {
        $trip = $this->trip(2400, ['ana', 'bea']);

        $first = $this->ledger()->postTrip($trip);
        $second = $this->ledger()->postTrip($trip->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $this->group->journalEntries()->count());
        $this->assertSame(-1200, app(BalanceService::class)->forMember($this->members['bea']));
    }

    public function test_a_solo_trip_creates_no_entry(): void
    {
        $trip = $this->trip(1500, ['ana']);

        $this->assertNull($this->ledger()->postTrip($trip));
        $this->assertSame(0, $this->group->journalEntries()->count());
    }

    public function test_a_settlement_cancels_debt_in_both_directions(): void
    {
        $this->ledger()->postTrip($this->trip(2400, ['ana', 'bea', 'carlos', 'diego']));

        $this->ledger()->postSettlement(
            from: $this->members['bea'],
            to: $this->members['ana'],
            amountCents: 600,
            settledOn: now(),
            method: 'bizum',
        );

        $balances = app(BalanceService::class);

        $this->assertSame(0, $balances->forMember($this->members['bea']));
        $this->assertSame(1200, $balances->forMember($this->members['ana']));
        $this->assertTrue($balances->isConsistent($this->group));
    }

    public function test_reversing_an_entry_leaves_the_original_untouched(): void
    {
        $trip = $this->trip(2400, ['ana', 'bea', 'carlos', 'diego']);
        $entry = $this->ledger()->postTrip($trip);

        $reversal = $this->ledger()->reverse($entry, 'Viaje apuntado dos veces');

        $this->assertSame(EntryKind::Reversal, $reversal->kind);
        $this->assertSame($entry->id, $reversal->reverses_id);
        $this->assertTrue($entry->fresh()->isReversed());

        // El original sigue en el libro, y la suma vuelve a cero para todos
        $this->assertSame(4, $entry->lines()->count());
        $this->assertSame(0, app(BalanceService::class)->forMember($this->members['bea']));
    }

    public function test_an_unbalanced_entry_is_rejected(): void
    {
        $this->expectException(UnbalancedEntryException::class);

        $this->ledger()->createEntry(
            group: $this->group,
            kind: EntryKind::Adjustment,
            description: 'Asiento manipulado',
            occurredOn: now(),
            lines: [
                ['group_member_id' => $this->members['ana']->id, 'amount_cents' => 1000],
                ['group_member_id' => $this->members['bea']->id, 'amount_cents' => -900],
            ],
        );
    }

    public function test_settlement_plan_clears_every_balance(): void
    {
        $this->ledger()->postTrip($this->trip(3000, ['ana', 'bea', 'carlos', 'diego'], driver: 'ana'));
        $this->ledger()->postTrip($this->trip(1800, ['ana', 'bea', 'carlos'], driver: 'bea'));

        $balances = app(BalanceService::class);
        $plan = $balances->settlementPlan($this->group);

        $this->assertNotEmpty($plan);

        foreach ($plan as $payment) {
            $this->ledger()->postSettlement(
                from: $payment['from'],
                to: $payment['to'],
                amountCents: $payment['amount_cents'],
                settledOn: now(),
            );
        }

        foreach ($this->members as $member) {
            $this->assertSame(0, $balances->forMember($member));
        }
    }
}
