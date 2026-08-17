<?php

namespace Tests\Feature;

use App\Models\FuelPrice;
use App\Models\FuelStation;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ninguna prueba debe salir a internet
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
            '*photon*' => Http::response([
                'features' => [[
                    'properties' => ['name' => 'Navacerrada', 'city' => 'Navacerrada', 'state' => 'Madrid', 'country' => 'España'],
                    'geometry' => ['coordinates' => [-4.003585, 40.788913]],
                ]],
            ]),
            '*' => Http::response([], 503),
        ]);

        config()->set('trayectos.ors.key', 'clave-de-pruebas');
    }

    private function seedPrice(): void
    {
        $station = FuelStation::create([
            'ideess' => 1, 'label' => 'Estación', 'municipality' => 'Madrid',
            'lat' => 40.4168, 'lon' => -3.7038,
        ]);

        FuelPrice::create([
            'fuel_station_id' => $station->id,
            'fuel_kind' => 'G95E5',
            'price_milli' => 1749,
            'observed_at' => now(),
        ]);
    }

    public function test_a_new_user_can_register_and_land_on_the_dashboard(): void
    {
        $response = $this->post('/registro', [
            'name' => 'Ana',
            'email' => 'ana@ejemplo.es',
            'password' => 'contrasena-larga',
            'password_confirmation' => 'contrasena-larga',
        ]);

        $response->assertRedirect('/panel');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'ana@ejemplo.es']);
    }

    public function test_registering_with_an_invite_code_joins_the_group(): void
    {
        $group = Group::factory()->create(['invite_code' => 'SIERRA26']);

        $this->post('/registro', [
            'name' => 'Bea',
            'email' => 'bea@ejemplo.es',
            'password' => 'contrasena-larga',
            'password_confirmation' => 'contrasena-larga',
            'invite_code' => 'sierra26',   // en minúsculas: debe dar igual
        ])->assertRedirect(route('groups.show', $group));

        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id' => User::where('email', 'bea@ejemplo.es')->value('id'),
        ]);
    }

    public function test_creating_a_group_makes_you_its_admin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/grupos', ['name' => 'Los del jueves'])->assertRedirect();

        $group = Group::firstOrFail();
        $this->assertSame('admin', $group->members()->where('user_id', $user->id)->value('role'));
        $this->assertNotEmpty($group->invite_code);
    }

    public function test_outsiders_cannot_see_a_group(): void
    {
        $group = Group::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('groups.show', $group))->assertForbidden();
        $this->actingAs($stranger)->get(route('ledger.index', $group))->assertForbidden();
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $group = Group::factory()->create();

        $this->get(route('groups.show', $group))->assertRedirect('/entrar');
        $this->get('/panel')->assertRedirect('/entrar');
    }

    public function test_a_trip_can_be_recorded_from_the_form(): void
    {
        $this->seedPrice();

        $group = Group::factory()->create();
        $ana = User::factory()->create(['name' => 'Ana']);
        $bea = User::factory()->create(['name' => 'Bea']);

        $anaMember = GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $ana->id]);
        $beaMember = GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $bea->id]);

        $vehicle = Vehicle::factory()->create(['owner_id' => $ana->id]);

        $response = $this->actingAs($ana)->post(route('trips.store', $group), [
            'vehicle_id' => $vehicle->id,
            'driver_member_id' => $anaMember->id,
            'travelled_on' => now()->toDateString(),
            'origin_label' => 'Madrid',
            'destination_label' => 'Navacerrada',
            'origin_lat' => 40.416775,
            'origin_lon' => -3.703790,
            'destination_lat' => 40.788913,
            'destination_lon' => -4.003585,
            'passengers' => [$anaMember->id, $beaMember->id],
        ]);

        $trip = Trip::firstOrFail();

        $response->assertRedirect(route('trips.show', [$group, $trip]));
        $this->assertSame(60000, $trip->distance_m);
        $this->assertSame(900, $trip->ascent_m);
        $this->assertNotNull($trip->journal_entry_id);
        $this->assertSame(2, $trip->passengers()->count());
    }

    public function test_the_driver_must_be_among_the_occupants(): void
    {
        $group = Group::factory()->create();
        $ana = User::factory()->create();
        $bea = User::factory()->create();

        $anaMember = GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $ana->id]);
        $beaMember = GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $bea->id]);
        $vehicle = Vehicle::factory()->create(['owner_id' => $ana->id]);

        $this->actingAs($ana)
            ->post(route('trips.store', $group), [
                'vehicle_id' => $vehicle->id,
                'driver_member_id' => $anaMember->id,
                'travelled_on' => now()->toDateString(),
                'origin_label' => 'Madrid',
                'destination_label' => 'Navacerrada',
                'distance_km' => 60,
                'passengers' => [$beaMember->id],   // falta la conductora
            ])
            ->assertSessionHasErrors('passengers');

        $this->assertSame(0, Trip::count());
    }

    public function test_a_trip_without_coordinates_or_distance_is_rejected(): void
    {
        $group = Group::factory()->create();
        $ana = User::factory()->create();
        $member = GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $ana->id]);
        $vehicle = Vehicle::factory()->create(['owner_id' => $ana->id]);

        $this->actingAs($ana)
            ->post(route('trips.store', $group), [
                'vehicle_id' => $vehicle->id,
                'driver_member_id' => $member->id,
                'travelled_on' => now()->toDateString(),
                'origin_label' => 'Sitio sin coordenadas',
                'destination_label' => 'Otro sitio',
                'passengers' => [$member->id],
            ])
            ->assertSessionHasErrors('distance_km');
    }

    public function test_a_vehicle_needs_the_data_its_technology_requires(): void
    {
        $user = User::factory()->create();

        // Un eléctrico sin consumo eléctrico ni autonomía no se puede calcular
        $this->actingAs($user)
            ->post(route('vehicles.store'), [
                'label' => 'Eléctrico incompleto',
                'powertrain' => 'BEV',
                'fuel_kind' => 'NONE',
                'seats' => 5,
                'kerb_weight_kg' => 1800,
            ])
            ->assertSessionHasErrors(['consumption_kwh_100', 'ev_range_km', 'battery_kwh_usable']);

        $this->assertSame(0, Vehicle::count());
    }

    public function test_a_settlement_can_be_recorded_from_the_web(): void
    {
        $group = Group::factory()->create();
        $ana = User::factory()->create();
        $bea = User::factory()->create();

        $anaMember = GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $ana->id]);
        $beaMember = GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $bea->id]);

        $this->actingAs($ana)->post(route('settlements.store', $group), [
            'from_member_id' => $beaMember->id,
            'to_member_id' => $anaMember->id,
            'amount' => '12,50',
            'settled_on' => now()->toDateString(),
            'method' => 'bizum',
        ])->assertRedirect();

        $this->assertDatabaseHas('settlements', ['amount_cents' => 1250, 'method' => 'bizum']);
        $this->assertSame(1250, $beaMember->fresh()->balanceCents());
        $this->assertSame(-1250, $anaMember->fresh()->balanceCents());
    }

    public function test_place_search_goes_through_our_backend(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/lugares?q=Navacerrada')
            ->assertOk()
            ->assertJsonPath('results.0.label', 'Navacerrada');
    }

    public function test_internal_tasks_require_the_shared_token(): void
    {
        config()->set('trayectos.internal_task_token', 'token-secreto');

        $this->postJson('/internal/sync-prices')->assertUnauthorized();
        $this->postJson('/internal/sync-prices', [], ['Authorization' => 'Bearer incorrecto'])->assertUnauthorized();
    }

    public function test_the_health_endpoint_answers_for_the_uptime_pinger(): void
    {
        $this->get('/up')->assertOk();
    }
}
