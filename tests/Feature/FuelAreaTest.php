<?php

namespace Tests\Feature;

use App\Enums\FuelKind;
use App\Models\FuelPrice;
use App\Models\FuelStation;
use App\Models\User;
use App\Services\Fuel\FuelPriceHistoryService;
use App\Support\FuelArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El filtro de ubicación de la pantalla de precios.
 *
 * Sin él el ámbito era la provincia entera, y Málaga va de Ronda a Nerja: la
 * gasolinera «más barata» podía estar a noventa kilómetros.
 */
class FuelAreaTest extends TestCase
{
    use RefreshDatabase;

    /** Plaza de la Merced, Málaga. Todo se mide desde aquí. */
    private const CENTRO_LAT = 36.7213;

    private const CENTRO_LON = -4.4214;

    private function estacion(string $label, float $lat, float $lon, string $municipio = 'Málaga', string $provincia = 'MÁLAGA', string $provinciaId = '29'): FuelStation
    {
        static $ideess = 5000;

        return FuelStation::create([
            'ideess' => $ideess++,
            'label' => $label,
            'municipality' => $municipio,
            'province' => $provincia,
            'province_id' => $provinciaId,
            'lat' => $lat,
            'lon' => $lon,
        ]);
    }

    private function precio(FuelStation $estacion, int $milli, ?string $cuando = null): FuelPrice
    {
        return FuelPrice::create([
            'fuel_station_id' => $estacion->id,
            'fuel_kind' => FuelKind::Diesel->value,
            'price_milli' => $milli,
            'observed_at' => $cuando ?? now()->subDay()->toDateTimeString(),
        ]);
    }

    private function history(): FuelPriceHistoryService
    {
        return app(FuelPriceHistoryService::class);
    }

    private function zona(int $radiusKm = 25): FuelArea
    {
        return new FuelArea(self::CENTRO_LAT, self::CENTRO_LON, $radiusKm, 'Málaga');
    }

    public function test_la_zona_deja_fuera_las_gasolineras_lejanas(): void
    {
        // A unos 3 km del centro
        $cerca = $this->estacion('La de al lado', self::CENTRO_LAT + 0.027, self::CENTRO_LON);
        // A unos 55 km: dentro de la provincia, pero no le sirve a nadie
        $lejos = $this->estacion('La de Marbella', self::CENTRO_LAT - 0.5, self::CENTRO_LON);

        $this->precio($cerca, 1700);
        $this->precio($lejos, 1400);

        $sinZona = $this->history()->cheapestStations(FuelKind::Diesel);
        $this->assertSame('La de Marbella', $sinZona->first()->label, 'Sin zona gana la barata de lejos');

        $conZona = $this->history()->cheapestStations(FuelKind::Diesel, area: $this->zona());
        $this->assertCount(1, $conZona);
        $this->assertSame('La de al lado', $conZona->first()->label);
    }

    public function test_una_estacion_en_la_esquina_de_la_caja_queda_fuera_del_radio(): void
    {
        /*
         * Este es el test que justifica el segundo paso del filtro. La caja
         * envolvente de 25 km mide 0,225° de latitud por 0,281° de longitud a
         * esta altura, así que este punto cae dentro de la caja... pero está a
         * unos 34 km en línea recta. Con sólo el filtro SQL aparecería, y el
         * listado enseñaría «34 km» bajo una zona de 25.
         */
        $esquina = $this->estacion('La de la esquina', self::CENTRO_LAT + 0.22, self::CENTRO_LON + 0.27);
        $this->precio($esquina, 1500);

        $enLaCaja = FuelStation::near(self::CENTRO_LAT, self::CENTRO_LON, 25)->pluck('id');
        $this->assertContains($esquina->id, $enLaCaja->all(), 'La caja envolvente sí la incluye');

        $this->assertEmpty(
            $this->zona()->stationIds()->all(),
            'Pero la distancia real la deja fuera'
        );
    }

    public function test_el_ranking_dice_a_cuanto_esta_cada_una(): void
    {
        $cerca = $this->estacion('La de al lado', self::CENTRO_LAT + 0.027, self::CENTRO_LON);
        $this->precio($cerca, 1600);

        $fila = $this->history()->cheapestStations(FuelKind::Diesel, area: $this->zona())->first();

        // 0,027° de latitud son unos 3 km
        $this->assertEqualsWithDelta(3.0, $fila->distance_km, 0.2);
    }

    public function test_cada_gasolinera_lleva_su_enlace_de_como_llegar(): void
    {
        $this->precio($this->estacion('La de al lado', 36.7480, -4.4214), 1600);

        $this->actingAs(User::factory()->create())
            ->get(route('prices'))
            ->assertOk()
            // Google Maps en el HTML porque funciona en todas partes; en
            // aparatos de Apple lo reescribe map-links.js a Mapas
            ->assertSee('google.com/maps/dir/?api=1&amp;destination=36.748,-4.4214', false)
            ->assertSee('aria-label="Cómo llegar a La de al lado"', false);
    }

    public function test_el_mapa_reparte_las_gasolineras_en_tercios_por_precio(): void
    {
        // Nueve gasolineras, todas cerca, con precios de 1500 a 1580
        foreach (range(0, 8) as $i) {
            $estacion = $this->estacion('Gasolinera '.$i, self::CENTRO_LAT + $i * 0.005, self::CENTRO_LON);
            $this->precio($estacion, 1500 + $i * 10);
        }

        $mapa = $this->history()->stationsForMap(FuelKind::Diesel, $this->zona());

        $this->assertCount(9, $mapa);
        // Vienen de más barata a más cara
        $this->assertSame('1,500', $mapa->first()->price);
        $this->assertSame('1,580', $mapa->last()->price);
        // Tres baratas, tres en la media, tres caras
        $this->assertSame([0, 0, 0, 1, 1, 1, 2, 2, 2], $mapa->pluck('tier')->all());
    }

    public function test_un_precio_disparatado_no_arrastra_a_las_demas(): void
    {
        // Ocho normales y una carísima. Si el nivel se midiera por rango de
        // precio, las ocho normales caerían todas en «de las más baratas».
        foreach (range(0, 7) as $i) {
            $this->precio($this->estacion('Normal '.$i, self::CENTRO_LAT + $i * 0.005, self::CENTRO_LON), 1500 + $i);
        }
        $this->precio($this->estacion('Carisima', self::CENTRO_LAT + 0.05, self::CENTRO_LON), 4000);

        $niveles = $this->history()->stationsForMap(FuelKind::Diesel, $this->zona())->pluck('tier');

        // Siguen repartidas en tres grupos de tres
        $this->assertSame(3, $niveles->filter(fn (int $t) => $t === 0)->count());
        $this->assertSame(3, $niveles->filter(fn (int $t) => $t === 1)->count());
        $this->assertSame(3, $niveles->filter(fn (int $t) => $t === 2)->count());
    }

    public function test_con_menos_de_tres_no_hay_ranking_que_ensenar(): void
    {
        $this->precio($this->estacion('Una', self::CENTRO_LAT + 0.01, self::CENTRO_LON), 1500);
        $this->precio($this->estacion('Otra', self::CENTRO_LAT + 0.02, self::CENTRO_LON), 1700);

        $niveles = $this->history()->stationsForMap(FuelKind::Diesel, $this->zona())->pluck('tier');

        $this->assertSame([1, 1], $niveles->all());
    }

    public function test_una_gasolinera_sin_coordenadas_no_va_al_mapa(): void
    {
        $conCoordenadas = $this->estacion('Con coordenadas', self::CENTRO_LAT + 0.01, self::CENTRO_LON);
        $this->precio($conCoordenadas, 1500);

        $sinCoordenadas = FuelStation::create([
            'ideess' => 5900,
            'label' => 'Sin coordenadas',
            'municipality' => 'Málaga',
            'province' => 'MÁLAGA',
            'province_id' => '29',
        ]);
        $this->precio($sinCoordenadas, 1400);

        $mapa = $this->history()->stationsForMap(FuelKind::Diesel, $this->zona());

        // No se puede pintar en un mapa lo que no tiene sitio
        $this->assertCount(1, $mapa);
        $this->assertSame('Con coordenadas', $mapa->first()->label);
    }

    public function test_el_mapa_solo_aparece_con_zona_elegida(): void
    {
        $user = User::factory()->create();

        foreach (range(0, 3) as $i) {
            $this->precio($this->estacion('Gasolinera '.$i, self::CENTRO_LAT + $i * 0.005, self::CENTRO_LON), 1500 + $i);
        }

        // Sin zona no se manda nada: serían las 1.930 estaciones sincronizadas
        $this->actingAs($user)
            ->get(route('prices'))
            ->assertOk()
            ->assertDontSee('El mapa de tu zona');

        $this->actingAs($user)->post(route('prices.area'), [
            'lat' => self::CENTRO_LAT,
            'lon' => self::CENTRO_LON,
        ]);

        $this->actingAs($user)
            ->get(route('prices'))
            ->assertOk()
            ->assertSee('El mapa de tu zona')
            ->assertSee('fuelMap({ stations:', false);
    }

    public function test_sin_zona_el_ranking_no_habla_de_distancias(): void
    {
        $this->precio($this->estacion('Cualquiera', self::CENTRO_LAT, self::CENTRO_LON), 1600);

        $this->assertNull($this->history()->cheapestStations(FuelKind::Diesel)->first()->distance_km);
    }

    public function test_la_serie_historica_tambien_respeta_la_zona(): void
    {
        $cerca = $this->estacion('La de al lado', self::CENTRO_LAT + 0.027, self::CENTRO_LON);
        $lejos = $this->estacion('La de Marbella', self::CENTRO_LAT - 0.5, self::CENTRO_LON);

        $this->precio($cerca, 1700);
        $this->precio($lejos, 1100);

        $serie = $this->history()->dailySeries(FuelKind::Diesel, area: $this->zona());

        $this->assertCount(1, $serie);
        $this->assertSame(1700, $serie->first()->avg_milli, 'La lejana no puede tirar de la media');
        $this->assertSame(1, $serie->first()->stations);
    }

    public function test_elegir_zona_la_recuerda_y_filtra_la_pantalla(): void
    {
        $cerca = $this->estacion('La de al lado', self::CENTRO_LAT + 0.027, self::CENTRO_LON);
        $lejos = $this->estacion('La de Marbella', self::CENTRO_LAT - 0.5, self::CENTRO_LON);

        foreach ([3, 2, 1] as $dias) {
            $this->precio($cerca, 1700, now()->subDays($dias)->toDateTimeString());
            $this->precio($lejos, 1100, now()->subDays($dias)->toDateTimeString());
        }

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('prices.area'), [
                'lat' => self::CENTRO_LAT,
                'lon' => self::CENTRO_LON,
                'label' => 'Málaga',
            ])
            ->assertRedirect(route('prices'));

        $this->actingAs($user)
            ->get(route('prices'))
            ->assertOk()
            ->assertSee('Málaga')
            ->assertSee('1,700')
            ->assertDontSee('La de Marbella');
    }

    public function test_las_coordenadas_se_redondean_al_guardarlas(): void
    {
        // Tres decimales son unos cien metros: de sobra para buscar
        // gasolineras, y así no queda guardado el portal exacto de nadie.
        $this->actingAs(User::factory()->create())
            ->post(route('prices.area'), [
                'lat' => 36.72134567,
                'lon' => -4.42149876,
            ]);

        $guardada = session(FuelArea::SESSION_KEY);

        $this->assertSame(36.721, $guardada['lat']);
        $this->assertSame(-4.421, $guardada['lon']);
    }

    public function test_un_radio_inventado_no_cuela(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('prices.area'), [
                'lat' => self::CENTRO_LAT,
                'lon' => self::CENTRO_LON,
                'radius_km' => 5000,
            ]);

        $this->assertSame(FuelArea::DEFAULT_RADIUS_KM, session(FuelArea::SESSION_KEY)['radius_km']);
    }

    public function test_cambiar_solo_el_radio_conserva_el_punto(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('prices.area'), [
            'lat' => self::CENTRO_LAT,
            'lon' => self::CENTRO_LON,
            'label' => 'Málaga',
        ]);

        $this->actingAs($user)->post(route('prices.area'), ['radius_km' => 50]);

        $guardada = session(FuelArea::SESSION_KEY);

        $this->assertSame(50, $guardada['radius_km']);
        $this->assertSame(36.721, $guardada['lat']);
        $this->assertSame('Málaga', $guardada['label']);
    }

    public function test_quitar_la_zona_vuelve_a_ensenarlo_todo(): void
    {
        $user = User::factory()->create();
        $lejos = $this->estacion('La de Marbella', self::CENTRO_LAT - 0.5, self::CENTRO_LON);
        $this->precio($lejos, 1100);

        $this->actingAs($user)->post(route('prices.area'), [
            'lat' => self::CENTRO_LAT,
            'lon' => self::CENTRO_LON,
        ]);

        $this->actingAs($user)->post(route('prices.area'), ['clear' => 1]);

        $this->assertNull(session(FuelArea::SESSION_KEY));

        $this->actingAs($user)
            ->get(route('prices'))
            ->assertOk()
            ->assertSee('La de Marbella');
    }

    public function test_avisa_cuando_no_hay_nada_en_el_radio(): void
    {
        $user = User::factory()->create();
        // A unos 30 km: fuera del radio de 5, pero dentro del de 50, así que
        // ampliar sí puede arreglarlo y tiene sentido ofrecerlo.
        $lejos = $this->estacion('La de Fuengirola', self::CENTRO_LAT - 0.27, self::CENTRO_LON);
        $this->precio($lejos, 1100);

        $this->actingAs($user)->post(route('prices.area'), [
            'lat' => self::CENTRO_LAT,
            'lon' => self::CENTRO_LON,
            'radius_km' => 5,
        ]);

        // Ni un «—» suelto ni una gráfica vacía: se dice qué pasa y cómo salir.
        // Y como la más cercana está a 55 km, sí tiene sentido ofrecer radios.
        $this->actingAs($user)
            ->get(route('prices'))
            ->assertOk()
            ->assertSee('Nada de Gasóleo A a 5 km')
            ->assertSee('La sincronizada más cercana está a')
            ->assertSee('aria-label="Ampliar el radio de búsqueda"', false)
            ->assertDontSee('La de Fuengirola');
    }

    public function test_si_lo_mas_cercano_esta_a_cientos_de_kilometros_lo_dice_en_vez_de_ofrecer_radios(): void
    {
        $user = User::factory()->create();

        // Mostoles: Madrid se sincroniza a proposito desde que hay gente alli, asi
        // que a alguien de Malaga le sale de verdad como lo mas cercano que hay.
        $mostoles = $this->estacion('Repsol Mostoles', 40.3223, -3.8649, 'Móstoles', 'MADRID', '28');
        $this->precio($mostoles, 1500);

        $this->actingAs($user)->post(route('prices.area'), [
            'lat' => self::CENTRO_LAT,
            'lon' => self::CENTRO_LON,
            'radius_km' => 50,
        ]);

        $this->actingAs($user)
            ->get(route('prices'))
            ->assertOk()
            ->assertSee('La sincronizada más cercana está a')
            ->assertSee('Móstoles')
            // Se dice qué cobertura hay, con el nombre y no con el número 28
            ->assertSee('Ahora mismo solo se descargan los precios de Madrid')
            // Ningun radio arregla 400 km: ofrecerlos como solucion seria mentir.
            // El panel de «Cambiar zona» sigue teniendolos, que para eso esta.
            ->assertDontSee('aria-label="Ampliar el radio de búsqueda"', false);
    }

    public function test_la_lista_de_provincias_se_lee_como_una_frase(): void
    {
        $user = User::factory()->create();

        /*
         * Una fila de comas sin la «y» del final no se acaba nunca: parece que
         * la frase se ha cortado a la mitad.
         */
        $this->precio($this->estacion('Repsol Mostoles', 40.3223, -3.8649, 'Móstoles', 'MADRID', '28'), 1500);
        $this->precio($this->estacion('Repsol Teatinos', self::CENTRO_LAT, self::CENTRO_LON), 1500);

        // Sin zona elegida, que es cuando la pantalla dice de dónde son
        $this->actingAs($user)
            ->get(route('prices'))
            ->assertOk()
            ->assertSee('De Madrid y Málaga.');
    }

    public function test_la_zona_no_se_le_pega_a_otra_persona(): void
    {
        $ismael = User::factory()->create();
        $otra = User::factory()->create();

        // Hace falta algún precio: sin ninguno la pantalla enseña la tarjeta de
        // «todavía no hay datos» y la barra de zona ni se dibuja.
        $this->precio($this->estacion('Cualquiera', self::CENTRO_LAT, self::CENTRO_LON), 1600);

        $this->actingAs($ismael)->post(route('prices.area'), [
            'lat' => self::CENTRO_LAT,
            'lon' => self::CENTRO_LON,
            'label' => 'Málaga',
        ]);

        $this->flushSession();

        $this->actingAs($otra)
            ->get(route('prices'))
            ->assertOk()
            ->assertSee('Toda la zona sincronizada');
    }
}
