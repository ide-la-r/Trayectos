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
use Illuminate\Http\Client\ConnectionException;
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
            /*
             * El buscador de sitios se comporta como el de verdad: conoce unos
             * cuantos y del resto no sabe nada. Devolver siempre un resultado
             * haría pasar tests que en producción fallarían, y al revés.
             */
            '*photon*' => function ($request) {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $params);

                $conocidos = [
                    'madrid' => ['Madrid', -3.703790, 40.416775],
                    'navacerrada' => ['Navacerrada', -4.003585, 40.788913],
                ];

                foreach ($conocidos as $clave => [$nombre, $lon, $lat]) {
                    if (str_contains(mb_strtolower($params['q'] ?? ''), $clave)) {
                        return Http::response(['features' => [[
                            'properties' => ['name' => $nombre, 'city' => $nombre, 'state' => 'Madrid', 'country' => 'España'],
                            'geometry' => ['coordinates' => [$lon, $lat]],
                        ]]]);
                    }
                }

                return Http::response(['features' => []]);
            },
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

    public function test_las_contrasenas_se_pueden_ver(): void
    {
        /*
         * Escribir una contraseña a ciegas en un móvil es la primera causa de
         * «no me deja entrar». El ojo de la derecha la enseña.
         *
         * Se comprueba que el botón es type="button": sin eso, dentro de un
         * formulario sería un botón de envío y darle al ojo mandaría el
         * formulario a medio rellenar.
         */
        foreach (['/entrar', '/registro'] as $pantalla) {
            $this->get($pantalla)
                ->assertOk()
                ->assertSee('Ver la contraseña')
                ->assertSee('type="button"', false);
        }

        // En el registro son dos campos, cada uno con el suyo
        $this->get('/registro')
            ->assertSee('name="password"', false)
            ->assertSee('name="password_confirmation"', false)
            ->assertSee('Ocho caracteres o más.');
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

    public function test_los_sitios_escritos_a_mano_se_buscan_solos(): void
    {
        /*
         * El caso que se encontró un amigo de Ismael la primera vez que usó la
         * aplicación: escribió el origen y el destino, no tocó el desplegable,
         * y el formulario le contestó «elige origen y destino del buscador».
         * La aplicación sabe perfectamente dónde están esos sitios, así que
         * ahora los busca ella y calcula los kilómetros.
         */
        $this->seedPrice();

        $group = Group::factory()->create();
        $ana = User::factory()->create();
        $anaMember = GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $ana->id]);
        $vehicle = Vehicle::factory()->create(['owner_id' => $ana->id]);

        $this->actingAs($ana)
            ->post(route('trips.store', $group), [
                'vehicle_id' => $vehicle->id,
                'driver_member_id' => $anaMember->id,
                'travelled_on' => now()->toDateString(),
                // Escritos a mano, SIN coordenadas y SIN kilómetros
                'origin_label' => 'Madrid',
                'destination_label' => 'Navacerrada',
                'passengers' => [$anaMember->id],
            ])
            ->assertSessionHasNoErrors();

        $trip = Trip::firstOrFail();

        // Las coordenadas las puso el buscador y la ruta salió de ellas
        $this->assertNotNull($trip->origin_lat);
        $this->assertNotNull($trip->destination_lat);
        $this->assertSame(60000, $trip->distance_m);
        $this->assertSame('ors', $trip->route_source);

        // Y el sitio se llama como lo escribió la persona, no como lo llame el mapa
        $this->assertSame('Madrid', $trip->origin_label);
    }

    public function test_si_el_buscador_no_conoce_el_sitio_se_pide_la_distancia(): void
    {
        $group = Group::factory()->create();
        $ana = User::factory()->create();
        $anaMember = GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $ana->id]);
        $vehicle = Vehicle::factory()->create(['owner_id' => $ana->id]);

        $this->actingAs($ana)
            ->post(route('trips.store', $group), [
                'vehicle_id' => $vehicle->id,
                'driver_member_id' => $anaMember->id,
                'travelled_on' => now()->toDateString(),
                'origin_label' => 'Villarriba de los Sitios Inventados',
                'destination_label' => 'Villabajo del Mismo Sitio',
                'passengers' => [$anaMember->id],
            ])
            ->assertSessionHasErrors('distance_km');

        // El aviso explica lo que pasa de verdad, no «elige del buscador»
        $this->assertStringContainsString(
            'No hemos encontrado esos sitios en el mapa',
            session('errors')->first('distance_km'),
        );
    }

    public function test_si_el_buscador_de_sitios_se_cae_el_formulario_no_revienta(): void
    {
        /*
         * Esto tumbó la aplicación con un 500 el primer día que la usó alguien
         * de fuera. Photon es un servicio ajeno y gratuito: se cae, tarda y a
         * veces corta. Cuando eso pasaba mientras se buscaban los sitios
         * escritos a mano, la excepción subía sin que nadie la recogiera.
         *
         * Antes daba igual porque el buscador sólo corría en el autocompletado
         * y el navegador se comía el fallo; desde que corre al guardar, se
         * lleva por delante el formulario entero.
         */
        Http::fake(['*photon*' => fn () => throw new ConnectionException('Se agotó el tiempo de espera')]);

        $group = Group::factory()->create();
        $ana = User::factory()->create();
        $anaMember = GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $ana->id]);
        $vehicle = Vehicle::factory()->create(['owner_id' => $ana->id]);

        $this->actingAs($ana)
            ->post(route('trips.store', $group), [
                'vehicle_id' => $vehicle->id,
                'driver_member_id' => $anaMember->id,
                'travelled_on' => now()->toDateString(),
                'origin_label' => 'Madrid',
                'destination_label' => 'Navacerrada',
                'passengers' => [$anaMember->id],
            ])
            // Se vuelve al formulario con un aviso, NO un 500
            ->assertRedirect()
            ->assertSessionHasErrors('distance_km');

        $this->assertSame(0, Trip::count());
    }

    public function test_si_se_caen_todos_los_servicios_el_viaje_se_apunta_igual(): void
    {
        /*
         * El peor caso: la ruta no responde, las altitudes tampoco, y la
         * persona sí ha elegido los sitios de la lista. El viaje tiene que
         * quedar apuntado —en línea recta corregida y en llano— y no devolver
         * un error. Un coste algo corto es mejor que perder el viaje.
         */
        $this->seedPrice();

        Http::fake([
            '*openrouteservice*' => fn () => throw new ConnectionException('caído'),
            '*opentopodata*' => fn () => throw new ConnectionException('caído'),
            '*' => Http::response([], 503),
        ]);

        $group = Group::factory()->create();
        $ana = User::factory()->create();
        $anaMember = GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $ana->id]);
        $vehicle = Vehicle::factory()->create(['owner_id' => $ana->id]);

        $this->actingAs($ana)
            ->post(route('trips.store', $group), [
                'vehicle_id' => $vehicle->id,
                'driver_member_id' => $anaMember->id,
                'travelled_on' => now()->toDateString(),
                'origin_label' => 'Madrid',
                'destination_label' => 'Navacerrada',
                'origin_lat' => 40.416775,
                'origin_lon' => -3.703790,
                'destination_lat' => 40.788913,
                'destination_lon' => -4.003585,
                'passengers' => [$anaMember->id],
            ])
            ->assertSessionHasNoErrors();

        $trip = Trip::firstOrFail();

        $this->assertGreaterThan(0, $trip->distance_m);
        $this->assertSame('haversine', $trip->route_source);
        $this->assertSame(0, $trip->ascent_m);   // sin altitudes, en llano
    }

    public function test_con_los_kilometros_a_mano_no_se_busca_nada(): void
    {
        // Quien pone los kilómetros no necesita ruta, y no hay que molestar al
        // servicio de mapas por gusto
        $this->seedPrice();

        $group = Group::factory()->create();
        $ana = User::factory()->create();
        $anaMember = GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $ana->id]);
        $vehicle = Vehicle::factory()->create(['owner_id' => $ana->id]);

        $this->actingAs($ana)
            ->post(route('trips.store', $group), [
                'vehicle_id' => $vehicle->id,
                'driver_member_id' => $anaMember->id,
                'travelled_on' => now()->toDateString(),
                'origin_label' => 'Un sitio cualquiera',
                'destination_label' => 'Otro sitio cualquiera',
                'distance_km' => '42',
                'passengers' => [$anaMember->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(42000, Trip::firstOrFail()->distance_m);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'photon'));
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
