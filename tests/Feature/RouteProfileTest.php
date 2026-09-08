<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Trips\RouteProfiler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El perfil de altitud del trayecto.
 *
 * El dato ya estaba: OpenRouteService devuelve la geometría con la altitud en
 * el tercer valor de cada punto y el viaje la guarda entera. Sólo faltaba
 * dibujarla.
 */
class RouteProfileTest extends TestCase
{
    use RefreshDatabase;

    private Group $group;

    private GroupMember $ana;

    private User $dueno;

    private Vehicle $coche;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dueno = User::factory()->create(['name' => 'Ana']);
        $this->group = Group::factory()->create(['created_by' => $this->dueno->id]);

        $this->ana = GroupMember::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $this->dueno->id,
            'role' => 'admin',
        ]);

        $this->coche = Vehicle::factory()->create(['owner_id' => $this->dueno->id]);
    }

    private function viaje(?array $geometry, string $source = 'ors'): Trip
    {
        return Trip::create([
            'group_id' => $this->group->id,
            'vehicle_id' => $this->coche->id,
            'driver_member_id' => $this->ana->id,
            'travelled_on' => now()->toDateString(),
            'origin_label' => 'Málaga',
            'destination_label' => 'Puerto de los Montes',
            'distance_m' => 40_000,
            'ascent_m' => 700,
            'descent_m' => 120,
            'route_source' => $source,
            'route_geometry' => $geometry,
            'total_cost_cents' => 2400,
            'cost_inputs' => ['test' => true],
            'formula_version' => 3,
            'created_by' => $this->dueno->id,
        ]);
    }

    /** Un puerto: sale bajo, sube, y baja un poco al final. [lon, lat, altitud] */
    private function puerto(): array
    {
        return [
            [-4.4214, 36.7213, 20],
            [-4.4000, 36.8000, 240],
            [-4.3800, 36.8800, 610],
            [-4.3600, 36.9500, 980],
            [-4.3400, 37.0100, 845],
        ];
    }

    private function profiler(): RouteProfiler
    {
        return app(RouteProfiler::class);
    }

    public function test_saca_el_perfil_de_la_geometria_guardada(): void
    {
        $perfil = $this->profiler()->profileFor($this->viaje($this->puerto()));

        $this->assertNotNull($perfil);
        $this->assertCount(5, $perfil->points);
        $this->assertSame(20, $perfil->start_m);
        $this->assertSame(845, $perfil->end_m);
        $this->assertSame(20, $perfil->min_m);
        $this->assertSame(980, $perfil->max_m);
        $this->assertSame(980, $perfil->peak->m);
    }

    public function test_la_distancia_se_acumula_a_lo_largo_del_recorrido(): void
    {
        $perfil = $this->profiler()->profileFor($this->viaje($this->puerto()));

        // El primer punto está en el kilómetro cero y cada uno va más lejos
        $kms = $perfil->points->pluck('km')->all();

        $this->assertSame(0.0, $kms[0]);
        $this->assertSame($kms, collect($kms)->sort()->values()->all());

        /*
         * 40 km, que es trips.distance_m, y no los ~33 que suman los tramos de
         * la geometría: está diezmada a ~120 puntos y se queda corta. Se escala
         * para no enseñar dos distancias distintas del mismo viaje.
         */
        $this->assertSame(40.0, $perfil->distance_km);
        $this->assertSame(40.0, round(end($kms), 1));
    }

    public function test_en_ida_y_vuelta_el_perfil_es_solo_la_ida(): void
    {
        $viaje = $this->viaje($this->puerto());
        // El estimador dobla distance_m en ida y vuelta, pero la geometría
        // sigue siendo la de la ida: sin tenerlo en cuenta saldria el doble.
        $viaje->update(['round_trip' => true, 'distance_m' => 80_000]);

        $perfil = $this->profiler()->profileFor($viaje->fresh());

        $this->assertTrue($perfil->round_trip);
        $this->assertSame(40.0, $perfil->distance_km);
    }

    public function test_el_pico_lleva_su_kilometro_no_solo_su_altura(): void
    {
        $perfil = $this->profiler()->profileFor($this->viaje($this->puerto()));

        // El punto más alto es el cuarto, así que no puede estar ni al principio
        // ni al final: es lo que sitúa la marca en el dibujo.
        $this->assertGreaterThan(0, $perfil->peak->km);
        $this->assertLessThan($perfil->distance_km, $perfil->peak->km);
    }

    public function test_sin_geometria_no_hay_perfil(): void
    {
        // La ruta se estimó en línea recta: hay desnivel, pero no recorrido
        $this->assertNull($this->profiler()->profileFor($this->viaje(null, 'haversine')));
    }

    public function test_una_geometria_sin_altitud_tampoco_sirve(): void
    {
        $plana = [[-4.4214, 36.7213], [-4.4000, 36.8000]];

        $this->assertNull($this->profiler()->profileFor($this->viaje($plana)));
    }

    public function test_un_solo_punto_no_es_un_recorrido(): void
    {
        $this->assertNull($this->profiler()->profileFor($this->viaje([[-4.4214, 36.7213, 20]])));
    }

    public function test_un_recorrido_llano_no_divide_entre_cero(): void
    {
        $llano = [
            [-4.4214, 36.7213, 15],
            [-4.4000, 36.7300, 15],
            [-4.3800, 36.7400, 15],
        ];

        $respuesta = $this->actingAs($this->dueno)
            ->get(route('trips.show', [$this->group, $this->viaje($llano)]));

        $respuesta->assertOk()->assertSee('Perfil del recorrido');
    }

    public function test_la_pantalla_del_viaje_dibuja_el_perfil(): void
    {
        $respuesta = $this->actingAs($this->dueno)
            ->get(route('trips.show', [$this->group, $this->viaje($this->puerto())]));

        $respuesta->assertOk()
            ->assertSee('Perfil del recorrido')
            ->assertSee('Más alto 980 m')
            // El dibujo tiene que contarse tambien para quien no lo ve
            ->assertSee('sale a 20 metros, sube hasta 980 en el kilómetro', false)
            ->assertSee('y llega a 845', false);
    }

    public function test_el_mapa_se_ofrece_pero_no_se_carga_solo(): void
    {
        $respuesta = $this->actingAs($this->dueno)
            ->get(route('trips.show', [$this->group, $this->viaje($this->puerto())]));

        $respuesta->assertOk()
            ->assertSee('Ver el recorrido en el mapa')
            // La geometria viaja con la pagina; MapLibre y las teselas no
            ->assertSee('tripMap({ geometry:', false)
            ->assertDontSee('maplibre-gl-BVifuw4r', false);
    }

    public function test_la_pantalla_no_ensena_perfil_cuando_no_hay_recorrido(): void
    {
        $respuesta = $this->actingAs($this->dueno)
            ->get(route('trips.show', [$this->group, $this->viaje(null, 'haversine')]));

        // Los metros de subida siguen estando arriba; lo que no hay es dibujo
        $respuesta->assertOk()
            ->assertSee('700')
            ->assertDontSee('Perfil del recorrido');
    }
}
