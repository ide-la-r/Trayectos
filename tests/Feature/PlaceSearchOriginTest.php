<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\FuelArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * De dónde saca el buscador «desde dónde» está preguntando cada persona.
 *
 * Es lo que hace que a alguien de Málaga le salga su Calle Larios y no la de
 * Toledo. Aquí se comprueba de dónde sale ese punto, no el orden en sí: eso
 * está en GeocodingOrderTest.
 */
class PlaceSearchOriginTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        Http::fake(['*photon*' => Http::response(['features' => []])]);
    }

    private function buscar(): void
    {
        $this->actingAs($this->user)
            ->get(route('api.places', ['q' => 'calle larios']))
            ->assertOk();
    }

    public function test_manda_la_zona_que_has_elegido_en_precios(): void
    {
        // Es donde uno ha dicho explícitamente dónde está
        $this->withSession([FuelArea::SESSION_KEY => ['lat' => 36.721, 'lon' => -4.421, 'radius_km' => 25]]);

        $this->buscar();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'lat=36.721'));
    }

    public function test_sin_zona_vale_el_origen_de_tu_ultimo_viaje(): void
    {
        // Quien sale siempre de Málaga va a seguir buscando cosas de por allí
        $this->trip(originLat: 36.75, travelledOn: '2026-09-01');
        $this->trip(originLat: 36.99, travelledOn: '2026-09-05');   // El último

        $this->buscar();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'lat=36.99'));
    }

    public function test_la_zona_manda_sobre_el_ultimo_viaje(): void
    {
        $this->trip(originLat: 36.99, travelledOn: '2026-09-05');
        $this->withSession([FuelArea::SESSION_KEY => ['lat' => 40.416, 'lon' => -3.703]]);

        $this->buscar();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'lat=40.416'));
    }

    public function test_quien_no_tiene_nada_busca_desde_el_centro_de_espana(): void
    {
        // Alguien que acaba de registrarse: ni zona ni viajes
        $this->buscar();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'lat=40.4'));
    }

    public function test_no_se_mira_el_viaje_de_un_grupo_que_no_es_tuyo(): void
    {
        /*
         * El origen de un viaje dice por dónde anda la gente: no puede salir
         * del grupo. Sería filtrar por dónde se mueven unos a otros.
         */
        $ajeno = User::factory()->create();
        $this->trip(originLat: 36.99, travelledOn: '2026-09-05', owner: $ajeno);

        $this->buscar();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'lat=40.4'));
    }

    private function trip(float $originLat, string $travelledOn, ?User $owner = null): Trip
    {
        $owner ??= $this->user;

        $group = Group::factory()->create();
        $member = GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $owner->id]);

        return Trip::create([
            'group_id' => $group->id,
            'vehicle_id' => Vehicle::factory()->create(['owner_id' => $owner->id])->id,
            'driver_member_id' => $member->id,
            'travelled_on' => $travelledOn,
            'origin_label' => 'Málaga',
            'destination_label' => 'Granada',
            'origin_lat' => $originLat,
            'origin_lon' => -4.42,
            'distance_m' => 125_000,
            'total_cost_cents' => 2200,
            'cost_inputs' => [],
            'formula_version' => 1,
        ]);
    }
}
