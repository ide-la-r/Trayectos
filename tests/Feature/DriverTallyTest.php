<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\JournalEntry;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Drivers\DriverTallyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverTallyTest extends TestCase
{
    use RefreshDatabase;

    private Group $group;

    private GroupMember $ana;

    private GroupMember $bea;

    private Vehicle $coche;

    protected function setUp(): void
    {
        parent::setUp();

        $dueno = User::factory()->create(['name' => 'Ana']);
        $this->group = Group::factory()->create(['created_by' => $dueno->id]);

        $this->ana = GroupMember::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $dueno->id,
            'role' => 'admin',
        ]);

        $this->bea = GroupMember::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => User::factory()->create(['name' => 'Bea'])->id,
        ]);

        $this->coche = Vehicle::factory()->create(['owner_id' => $dueno->id]);
    }

    /**
     * El recuento es una agregación SQL, así que las filas se crean directamente
     * en lugar de pasar por TripRecorder: no hace falta rutas, precios ni
     * asientos para comprobar una suma, y el test corre en milisegundos.
     */
    private function viaje(GroupMember $conductor, int $metros, ?JournalEntry $asiento = null, ?Group $group = null): Trip
    {
        return Trip::create([
            'group_id' => ($group ?? $this->group)->id,
            'vehicle_id' => $this->coche->id,
            'driver_member_id' => $conductor->id,
            'travelled_on' => now()->toDateString(),
            'origin_label' => 'Málaga',
            'destination_label' => 'Ronda',
            'distance_m' => $metros,
            'total_cost_cents' => 2400,
            'cost_inputs' => ['test' => true],
            'formula_version' => 3,
            'journal_entry_id' => $asiento?->id,
            'created_by' => $conductor->user_id,
        ]);
    }

    private function asiento(array $extra = []): JournalEntry
    {
        return JournalEntry::create(array_merge([
            'group_id' => $this->group->id,
            'kind' => 'trip',
            'description' => 'Viaje de prueba',
            'occurred_on' => now()->toDateString(),
        ], $extra));
    }

    private function tally(): \Illuminate\Support\Collection
    {
        return app(DriverTallyService::class)->forGroup($this->group);
    }

    public function test_cuenta_las_veces_y_los_kilometros_de_cada_conductor(): void
    {
        $this->viaje($this->ana, 60_000);
        $this->viaje($this->ana, 15_500);
        $this->viaje($this->bea, 40_000);

        $tally = $this->tally();

        $this->assertSame(2, $tally[$this->ana->id]->trips);
        $this->assertSame(75_500, $tally[$this->ana->id]->meters);
        $this->assertSame(1, $tally[$this->bea->id]->trips);
        $this->assertSame(40_000, $tally[$this->bea->id]->meters);
    }

    public function test_quien_no_ha_conducido_no_aparece_en_el_recuento(): void
    {
        $this->viaje($this->ana, 30_000);

        $this->assertFalse($this->tally()->has($this->bea->id));
    }

    public function test_un_viaje_anulado_no_cuenta(): void
    {
        // Anular no borra la fila del viaje: contraasienta en el libro. Si el
        // recuento no lo excluye, sube con viajes que ya no valen.
        $asiento = $this->asiento();
        $this->viaje($this->ana, 60_000, $asiento);

        $this->assertSame(1, $this->tally()[$this->ana->id]->trips);

        $this->asiento(['kind' => 'reversal', 'reverses_id' => $asiento->id]);

        $this->assertFalse($this->tally()->has($this->ana->id));
    }

    public function test_no_mezcla_los_viajes_de_otro_grupo(): void
    {
        $otro = Group::factory()->create(['created_by' => $this->ana->user_id]);
        $suMiembro = GroupMember::factory()->create([
            'group_id' => $otro->id,
            'user_id' => $this->ana->user_id,
        ]);

        $this->viaje($suMiembro, 99_000, group: $otro);
        $this->viaje($this->ana, 10_000);

        $tally = $this->tally();

        $this->assertSame(1, $tally[$this->ana->id]->trips);
        $this->assertSame(10_000, $tally[$this->ana->id]->meters);
    }

    public function test_la_pantalla_del_grupo_ensena_el_recuento(): void
    {
        $this->viaje($this->ana, 60_000);
        $this->viaje($this->ana, 40_000);

        $this->actingAs($this->ana->user)
            ->get(route('groups.show', $this->group))
            ->assertOk()
            ->assertSee('2 veces al volante')
            ->assertSee('Todavía no ha conducido');
    }
}
