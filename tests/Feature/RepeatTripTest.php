<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Repetir un viaje que ya se hizo, y los «de siempre» que salen del histórico.
 *
 * No hay tabla de plantillas: un viaje repetido ya está apuntado varias veces.
 */
class RepeatTripTest extends TestCase
{
    use RefreshDatabase;

    private Group $group;

    private User $user;

    private GroupMember $ana;

    private GroupMember $bea;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group = Group::factory()->create();
        $this->user = User::factory()->create(['name' => 'Ana']);

        $this->ana = GroupMember::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $this->user->id,
        ]);

        $this->bea = GroupMember::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => User::factory()->create(['name' => 'Bea'])->id,
        ]);

        $this->vehicle = Vehicle::factory()->create([
            'owner_id' => $this->user->id,
            'label' => 'El Ibiza',
        ]);
    }

    private function viaje(array $overrides = []): Trip
    {
        $trip = Trip::create([
            'group_id' => $this->group->id,
            'vehicle_id' => $this->vehicle->id,
            'driver_member_id' => $this->ana->id,
            'travelled_on' => now()->subDays(7)->toDateString(),
            'origin_label' => 'Málaga',
            'destination_label' => 'Granada',
            'origin_lat' => 36.7213,
            'origin_lon' => -4.4214,
            'destination_lat' => 37.1773,
            'destination_lon' => -3.5986,
            'round_trip' => false,
            'distance_m' => 125_000,
            'ascent_m' => 900,
            'descent_m' => 400,
            'luggage_kg' => 15,
            'route_source' => 'ors',
            'total_cost_cents' => 2200,
            'cost_inputs' => ['breakdown' => ['litres' => 8.1, 'kwh' => 0]],
            'formula_version' => 1,
            'notes' => 'Paramos a comer en Loja',
            ...$overrides,
        ]);

        $trip->passengers()->create(['group_member_id' => $this->ana->id, 'weight' => 1.00]);
        $trip->passengers()->create(['group_member_id' => $this->bea->id, 'weight' => 0.50]);

        return $trip;
    }

    public function test_repetir_deja_el_formulario_relleno_con_aquel_viaje(): void
    {
        $trip = $this->viaje();

        $this->actingAs($this->user)
            ->get(route('trips.repeat', [$this->group, $trip]))
            ->assertRedirect(route('trips.create', $this->group))
            ->assertSessionHasInput([
                'origin_label' => 'Málaga',
                'destination_label' => 'Granada',
                'vehicle_id' => $this->vehicle->id,
                'driver_member_id' => $this->ana->id,
                'luggage_kg' => 15,
            ]);

        /*
         * Y el formulario sale ya escrito. El buscador de sitios no se rellena
         * con un value=: lo monta Alpine, así que el sitio y sus coordenadas
         * viajan en el x-data. Las coordenadas son lo que de verdad importa —
         * sin ellas habría que volver a elegir el sitio para que salga la ruta.
         */
        $this->actingAs($this->user)
            ->get(route('trips.create', $this->group))
            ->assertOk()
            ->assertSee("label: 'Granada'", false)
            ->assertSee('36.7213', false)
            ->assertSee('-3.5986', false)
            // Éste sí es un campo normal, y confirma que el relleno llega entero
            ->assertSee('value="15"', false);
    }

    public function test_se_repite_el_trayecto_pero_no_el_dia_ni_las_notas(): void
    {
        $trip = $this->viaje();

        $this->actingAs($this->user)
            ->get(route('trips.repeat', [$this->group, $trip]))
            ->assertSessionMissing('_old_input.travelled_on')
            ->assertSessionMissing('_old_input.notes');

        // La fecha vuelve a ser hoy, que es lo que trae el formulario por defecto
        $this->actingAs($this->user)
            ->get(route('trips.create', $this->group))
            ->assertSee('value="'.now()->toDateString().'"', false)
            ->assertDontSee('Paramos a comer en Loja');
    }

    public function test_se_repiten_los_acompanantes_con_su_parte(): void
    {
        $trip = $this->viaje();

        $this->actingAs($this->user)
            ->get(route('trips.repeat', [$this->group, $trip]))
            ->assertSessionHasInput('passengers', [$this->ana->id, $this->bea->id])
            // Bea iba a medio viaje y tiene que seguir yendo a medio
            ->assertSessionHasInput('weights', [
                $this->ana->id => '1',
                $this->bea->id => '0.5',
            ]);
    }

    public function test_no_se_repite_un_coche_que_ya_no_esta(): void
    {
        /*
         * Si el desplegable no encuentra el valor, el navegador se queda con la
         * primera opción de la lista: el viaje se apuntaría con OTRO coche y su
         * consumo. Mejor no rellenarlo y que haya que elegir.
         */
        $trip = $this->viaje();
        $this->vehicle->update(['active' => false]);

        $this->actingAs($this->user)
            ->get(route('trips.repeat', [$this->group, $trip]))
            ->assertSessionMissing('_old_input.vehicle_id')
            // El resto del viaje sí se repite
            ->assertSessionHasInput('origin_label', 'Málaga');
    }

    public function test_no_se_repite_a_quien_ya_no_esta_en_el_grupo(): void
    {
        $trip = $this->viaje();
        $this->bea->update(['active' => false]);

        $this->actingAs($this->user)
            ->get(route('trips.repeat', [$this->group, $trip]))
            ->assertSessionHasInput('passengers', [$this->ana->id]);
    }

    public function test_la_distancia_puesta_a_mano_se_conserva(): void
    {
        // Quien corrigió los kilómetros a mano no tiene que volver a hacerlo
        $trip = $this->viaje([
            'route_source' => 'manual',
            'distance_m' => 138_000,
            'ascent_m' => 950,
            'descent_m' => 420,
        ]);

        $this->actingAs($this->user)
            ->get(route('trips.repeat', [$this->group, $trip]))
            ->assertSessionHasInput([
                'distance_km' => 138.0,
                'ascent_m' => 950,
                'descent_m' => 420,
            ]);
    }

    public function test_en_ida_y_vuelta_la_distancia_a_mano_se_parte(): void
    {
        /*
         * El formulario pide la ida y el estimador la dobla, así que lo guardado
         * viene ya doblado: devolverlo tal cual apuntaría el doble de camino.
         */
        $trip = $this->viaje([
            'route_source' => 'manual',
            'round_trip' => true,
            'distance_m' => 276_000,
            'ascent_m' => 1370,
            'descent_m' => 1370,
        ]);

        $this->actingAs($this->user)
            ->get(route('trips.repeat', [$this->group, $trip]))
            ->assertSessionHasInput('distance_km', 138.0)
            // El desnivel de ida y vuelta viene cruzado y no se puede deshacer:
            // antes en blanco que inventarse un reparto
            ->assertSessionMissing('_old_input.ascent_m');
    }

    public function test_no_se_puede_repetir_un_viaje_de_otro_grupo(): void
    {
        $otro = Group::factory()->create();
        GroupMember::factory()->create(['group_id' => $otro->id, 'user_id' => $this->user->id]);

        $trip = $this->viaje();

        $this->actingAs($this->user)
            ->get(route('trips.repeat', [$otro, $trip]))
            ->assertNotFound();
    }

    public function test_los_de_siempre_salen_en_la_pantalla_de_apuntar(): void
    {
        // Dos veces el mismo trayecto: ése ya es «de siempre»
        $this->viaje();
        $this->viaje();
        // Y uno suelto, que no lo es
        $this->viaje(['destination_label' => 'Ronda']);

        $this->actingAs($this->user)
            ->get(route('trips.create', $this->group))
            ->assertOk()
            ->assertSee('Los de siempre')
            ->assertSee('Málaga → Granada')
            ->assertSee('2 veces')
            ->assertSee('El Ibiza')
            ->assertDontSee('Málaga → Ronda');
    }

    public function test_sin_viajes_repetidos_no_hay_atajos(): void
    {
        $this->viaje();

        $this->actingAs($this->user)
            ->get(route('trips.create', $this->group))
            ->assertOk()
            ->assertDontSee('Los de siempre');
    }
}
