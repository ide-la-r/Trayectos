<?php

namespace Tests\Feature;

use App\Enums\EntryKind;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Drivers\DriverSuggestionService;
use App\Services\Ledger\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverSuggestionTest extends TestCase
{
    use RefreshDatabase;

    private Group $group;

    /** @var array<string, GroupMember> */
    private array $members = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->group = Group::factory()->create();

        foreach (['ana', 'bea', 'carlos'] as $name) {
            $user = User::factory()->create(['name' => ucfirst($name)]);

            $this->members[$name] = GroupMember::factory()->create([
                'group_id' => $this->group->id,
                'user_id' => $user->id,
            ]);

            Vehicle::factory()->create(['owner_id' => $user->id, 'seats' => 5]);
        }
    }

    /** Mete deuda a mano en el libro, sin pasar por un viaje completo. */
    private function owe(string $debtor, string $creditor, int $cents): void
    {
        app(LedgerService::class)->createEntry(
            group: $this->group,
            kind: EntryKind::Adjustment,
            description: 'Saldo de partida',
            occurredOn: now(),
            lines: [
                ['group_member_id' => $this->members[$debtor]->id, 'amount_cents' => -$cents],
                ['group_member_id' => $this->members[$creditor]->id, 'amount_cents' => $cents],
            ],
        );
    }

    public function test_it_puts_the_biggest_debtor_first(): void
    {
        $this->owe('carlos', 'ana', 4000);
        $this->owe('bea', 'ana', 1500);

        $suggestions = app(DriverSuggestionService::class)->suggest($this->group);

        $this->assertSame('Carlos', $suggestions[0]['member']->user->name);
        $this->assertSame(-4000, $suggestions[0]['balance_cents']);
        $this->assertStringContainsString('Debe 40,00 €', $suggestions[0]['reason']);
    }

    public function test_recent_driving_breaks_the_tie(): void
    {
        // Bea y Carlos deben lo mismo, pero Carlos ha conducido dos veces
        $this->owe('carlos', 'ana', 2000);
        $this->owe('bea', 'ana', 2000);

        $vehicle = Vehicle::where('owner_id', $this->members['carlos']->user_id)->firstOrFail();

        foreach (range(1, 2) as $index) {
            Trip::create([
                'group_id' => $this->group->id,
                'vehicle_id' => $vehicle->id,
                'driver_member_id' => $this->members['carlos']->id,
                'travelled_on' => now()->subDays($index)->toDateString(),
                'origin_label' => 'A',
                'destination_label' => 'B',
                'distance_m' => 10_000,
                'total_cost_cents' => 500,
                'cost_inputs' => [],
                'formula_version' => 3,
            ]);
        }

        $suggestions = app(DriverSuggestionService::class)->suggest($this->group);

        $this->assertSame('Bea', $suggestions[0]['member']->user->name);
    }

    public function test_people_without_a_big_enough_car_are_not_suggested(): void
    {
        $this->owe('carlos', 'ana', 5000);

        Vehicle::where('owner_id', $this->members['carlos']->user_id)->update(['seats' => 2]);

        $suggestions = app(DriverSuggestionService::class)->suggest($this->group, neededSeats: 4);
        $names = array_map(fn (array $row) => $row['member']->user->name, $suggestions);

        // Carlos es el que más debe, pero su coche no cabe: sugerirlo no sirve
        $this->assertNotContains('Carlos', $names);
    }

    public function test_it_returns_at_most_the_configured_number_of_options(): void
    {
        config()->set('trayectos.driver_suggestion.suggestions', 2);

        $this->assertCount(2, app(DriverSuggestionService::class)->suggest($this->group));
    }
}
