<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Trips\TripDraft;
use App\Services\Trips\VehicleComparisonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VehicleComparisonTest extends TestCase
{
    use RefreshDatabase;

    private Group $group;

    private GroupMember $ana;

    private GroupMember $bea;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Http::fake([
            '*openrouteservice*' => Http::response([
                'features' => [[
                    'properties' => [
                        'summary' => ['distance' => 60000, 'duration' => 3300],
                        'ascent' => 900,
                        'descent' => 120,
                    ],
                    'geometry' => ['coordinates' => [[-3.70, 40.41, 650], [-4.00, 40.78, 1850]]],
                ]],
            ]),
            '*' => Http::response([], 200),
        ]);

        $anaUser = User::factory()->create(['name' => 'Ana']);
        $beaUser = User::factory()->create(['name' => 'Bea']);

        $this->group = Group::factory()->create(['created_by' => $anaUser->id]);
        $this->ana = GroupMember::factory()->create(['group_id' => $this->group->id, 'user_id' => $anaUser->id, 'role' => 'admin']);
        $this->bea = GroupMember::factory()->create(['group_id' => $this->group->id, 'user_id' => $beaUser->id]);
    }

    private function coche(GroupMember $duenyo, array $attrs = []): Vehicle
    {
        return Vehicle::factory()->create(array_merge([
            'owner_id' => $duenyo->user_id,
            'active' => true,
            'seats' => 5,
        ], $attrs));
    }

    private function borrador(Vehicle $vehicle): TripDraft
    {
        return new TripDraft(
            group: $this->group,
            vehicle: $vehicle,
            driver: $this->ana,
            travelledOn: now(),
            originLabel: 'Málaga',
            destinationLabel: 'Ronda',
            passengerWeights: [$this->ana->id => 1.0, $this->bea->id => 1.0],
            manualDistanceM: 60_000,
            manualAscentM: 900,
            manualDescentM: 120,
        );
    }

    public function test_con_un_solo_coche_no_devuelve_comparacion(): void
    {
        $coche = $this->coche($this->ana);

        $filas = app(VehicleComparisonService::class)->compare($this->borrador($coche));

        // Una tabla de una fila no compara nada: es ruido en el formulario
        $this->assertTrue($filas->isEmpty());
    }

    public function test_ordena_por_coste_y_marca_el_mas_barato(): void
    {
        $gastón = $this->coche($this->ana, ['label' => 'El gastón', 'consumption_l_100' => 9.5]);
        $ahorrador = $this->coche($this->bea, ['label' => 'El ahorrador', 'consumption_l_100' => 4.5]);

        $filas = app(VehicleComparisonService::class)->compare($this->borrador($gastón), payerCount: 2);

        $this->assertCount(2, $filas);

        // El de menos consumo primero, y marcado
        $this->assertSame($ahorrador->id, $filas->first()->vehicle->id);
        $this->assertTrue($filas->first()->cheapest);
        $this->assertSame(0, $filas->first()->extra_cents);

        // El caro cuesta más y sabe cuánto más
        $this->assertGreaterThan($filas->first()->cost_cents, $filas->last()->cost_cents);
        $this->assertSame(
            $filas->last()->cost_cents - $filas->first()->cost_cents,
            $filas->last()->extra_cents,
        );
        $this->assertFalse($filas->last()->cheapest);
    }

    public function test_marca_cual_es_el_coche_elegido_ahora_mismo(): void
    {
        $elegido = $this->coche($this->ana, ['consumption_l_100' => 9.5]);
        $this->coche($this->bea, ['consumption_l_100' => 4.5]);

        $filas = app(VehicleComparisonService::class)->compare($this->borrador($elegido));

        $this->assertTrue($filas->firstWhere('vehicle.id', $elegido->id)->is_selected);
        $this->assertCount(1, $filas->where('is_selected', true));
    }

    public function test_reparte_el_coste_entre_quienes_pagan(): void
    {
        $uno = $this->coche($this->ana);
        $this->coche($this->bea, ['consumption_l_100' => 4.5]);

        $filas = app(VehicleComparisonService::class)->compare($this->borrador($uno), payerCount: 4);

        foreach ($filas as $fila) {
            $this->assertSame((int) round($fila->cost_cents / 4), $fila->per_payer_cents);
        }
    }

    public function test_descarta_los_coches_sin_plazas_para_todos(): void
    {
        $grande = $this->coche($this->ana, ['seats' => 5]);
        $this->coche($this->bea, ['seats' => 2]);   // van 2 personas, este cabe justo

        $borrador = new TripDraft(
            group: $this->group,
            vehicle: $grande,
            driver: $this->ana,
            travelledOn: now(),
            originLabel: 'Málaga',
            destinationLabel: 'Ronda',
            // Tres ocupantes: el biplaza deja de ser candidato
            passengerWeights: [$this->ana->id => 1.0, $this->bea->id => 1.0, -1 => 1.0],
            manualDistanceM: 60_000,
        );

        $filas = app(VehicleComparisonService::class)->compare($borrador);

        $this->assertTrue($filas->isEmpty(), 'Sólo queda un coche con plazas, así que no hay comparación');
    }

    public function test_descarta_los_coches_de_fuera_del_grupo(): void
    {
        $mio = $this->coche($this->ana);
        $this->coche($this->bea);

        // Coche de alguien que no está en el grupo
        Vehicle::factory()->create(['owner_id' => User::factory()->create()->id, 'active' => true, 'seats' => 5]);

        $filas = app(VehicleComparisonService::class)->compare($this->borrador($mio));

        $this->assertCount(2, $filas);
    }

    public function test_el_endpoint_de_estimacion_devuelve_la_comparacion(): void
    {
        $mio = $this->coche($this->ana, ['consumption_l_100' => 9.5]);
        $this->coche($this->bea, ['consumption_l_100' => 4.5]);

        $this->actingAs($this->ana->user)
            ->postJson('/api/estimacion', [
                'group_id' => $this->group->id,
                'vehicle_id' => $mio->id,
                'distance_km' => 60,
                'ascent_m' => 900,
                'descent_m' => 120,
                'occupants' => 2,
                'payers' => 2,
            ])
            ->assertOk()
            ->assertJsonCount(2, 'comparison')
            ->assertJsonStructure(['comparison' => [['vehicle_id', 'label', 'owner', 'cost', 'per_payer', 'extra', 'cheapest', 'selected']]]);
    }
}
