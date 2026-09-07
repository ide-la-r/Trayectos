<?php

namespace Tests\Feature;

use App\Enums\FuelKind;
use App\Models\FuelPrice;
use App\Models\FuelStation;
use App\Models\User;
use App\Services\Fuel\FuelPriceHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FuelPriceHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function estacion(array $attrs = []): FuelStation
    {
        static $ideess = 1000;

        return FuelStation::create(array_merge([
            'ideess' => $ideess++,
            'label' => 'Gasolinera de prueba',
            'municipality' => 'Málaga',
            'province_id' => '29',
            'lat' => 36.72,
            'lon' => -4.42,
        ], $attrs));
    }

    private function precio(FuelStation $estacion, int $milli, string $cuando, FuelKind $kind = FuelKind::Diesel): FuelPrice
    {
        return FuelPrice::create([
            'fuel_station_id' => $estacion->id,
            'fuel_kind' => $kind->value,
            'price_milli' => $milli,
            'observed_at' => $cuando,
        ]);
    }

    private function history(): FuelPriceHistoryService
    {
        return app(FuelPriceHistoryService::class);
    }

    public function test_agrupa_por_dia_el_minimo_y_la_media(): void
    {
        $a = $this->estacion(['label' => 'Barata']);
        $b = $this->estacion(['label' => 'Cara']);

        // Dos observaciones el mismo día: tienen que colapsar en una fila
        $this->precio($a, 1500, now()->subDays(2)->setTime(6, 15)->toDateTimeString());
        $this->precio($b, 1700, now()->subDays(2)->setTime(6, 15)->toDateTimeString());
        $this->precio($a, 1520, now()->subDays(2)->setTime(11, 15)->toDateTimeString());

        $serie = $this->history()->dailySeries(FuelKind::Diesel);

        $this->assertCount(1, $serie);
        $this->assertSame(1500, $serie->first()->min_milli);
        $this->assertSame((int) round((1500 + 1700 + 1520) / 3), $serie->first()->avg_milli);
        // Dos estaciones distintas, aunque haya tres observaciones
        $this->assertSame(2, $serie->first()->stations);
    }

    public function test_la_serie_va_de_mas_antiguo_a_mas_reciente(): void
    {
        $e = $this->estacion();

        $this->precio($e, 1600, now()->subDays(3)->toDateTimeString());
        $this->precio($e, 1500, now()->subDays(1)->toDateTimeString());
        $this->precio($e, 1550, now()->subDays(2)->toDateTimeString());

        $dias = $this->history()->dailySeries(FuelKind::Diesel)->pluck('day')->all();

        $this->assertSame($dias, collect($dias)->sort()->values()->all());
    }

    public function test_no_mezcla_carburantes(): void
    {
        $e = $this->estacion();

        $this->precio($e, 1600, now()->subDay()->toDateTimeString(), FuelKind::Diesel);
        $this->precio($e, 1750, now()->subDay()->toDateTimeString(), FuelKind::Gasoline95);

        $this->assertSame(1600, $this->history()->dailySeries(FuelKind::Diesel)->first()->avg_milli);
        $this->assertSame(1750, $this->history()->dailySeries(FuelKind::Gasoline95)->first()->avg_milli);
    }

    public function test_deja_fuera_lo_anterior_a_la_ventana(): void
    {
        $e = $this->estacion();

        $this->precio($e, 1200, now()->subDays(40)->toDateTimeString());
        $this->precio($e, 1600, now()->subDay()->toDateTimeString());

        $serie = $this->history()->dailySeries(FuelKind::Diesel, days: 30);

        $this->assertCount(1, $serie);
        $this->assertSame(1600, $serie->first()->avg_milli);
    }

    public function test_detecta_que_el_precio_sube(): void
    {
        $e = $this->estacion();

        $this->precio($e, 1500, now()->subDays(4)->toDateTimeString());
        $this->precio($e, 1560, now()->subDays(3)->toDateTimeString());
        $this->precio($e, 1620, now()->subDays(2)->toDateTimeString());

        $resumen = $this->history()->summary(FuelKind::Diesel);

        $this->assertSame('up', $resumen->direction);
        $this->assertSame(120, $resumen->change_milli);
        $this->assertStringContainsString('llenar hoy', $resumen->verdict);
    }

    public function test_detecta_que_el_precio_baja(): void
    {
        $e = $this->estacion();

        $this->precio($e, 1700, now()->subDays(4)->toDateTimeString());
        $this->precio($e, 1650, now()->subDays(3)->toDateTimeString());
        $this->precio($e, 1600, now()->subDays(2)->toDateTimeString());

        $resumen = $this->history()->summary(FuelKind::Diesel);

        $this->assertSame('down', $resumen->direction);
        $this->assertStringContainsString('esperar', $resumen->verdict);
    }

    public function test_una_variacion_minima_se_considera_estable(): void
    {
        $e = $this->estacion();

        // Cuatro milésimas arriba en tres días es ruido de que unas informen
        // antes que otras, no un movimiento del mercado
        $this->precio($e, 1600, now()->subDays(4)->toDateTimeString());
        $this->precio($e, 1602, now()->subDays(3)->toDateTimeString());
        $this->precio($e, 1604, now()->subDays(2)->toDateTimeString());

        $this->assertSame('flat', $this->history()->summary(FuelKind::Diesel)->direction);
    }

    public function test_con_menos_de_tres_dias_no_inventa_tendencia(): void
    {
        $e = $this->estacion();

        $this->precio($e, 1500, now()->subDays(2)->toDateTimeString());
        $this->precio($e, 1900, now()->subDay()->toDateTimeString());

        $resumen = $this->history()->summary(FuelKind::Diesel);

        $this->assertSame('unknown', $resumen->direction);
        $this->assertNull($resumen->change_milli);
        $this->assertStringContainsString('días suficientes', $resumen->verdict);
    }

    public function test_las_mas_baratas_usan_solo_la_ultima_observacion(): void
    {
        $barata = $this->estacion(['label' => 'La barata']);
        $cara = $this->estacion(['label' => 'La cara']);

        // La barata estuvo carísima ayer: si se mira el histórico y no la
        // última observación, aparecería en el puesto equivocado
        $this->precio($barata, 1900, now()->subDay()->toDateTimeString());
        $this->precio($barata, 1400, now()->toDateTimeString());
        $this->precio($cara, 1700, now()->toDateTimeString());

        $ranking = $this->history()->cheapestStations(FuelKind::Diesel);

        $this->assertCount(2, $ranking, 'Cada estación una sola vez');
        $this->assertSame('La barata', $ranking->first()->label);
        $this->assertSame(1400, (int) $ranking->first()->price_milli);
    }

    public function test_la_pantalla_responde_y_ensena_el_precio(): void
    {
        $e = $this->estacion();
        $this->precio($e, 1500, now()->subDays(3)->toDateTimeString());
        $this->precio($e, 1550, now()->subDays(2)->toDateTimeString());
        $this->precio($e, 1600, now()->subDay()->toDateTimeString());

        $this->actingAs(User::factory()->create())
            ->get(route('prices'))
            ->assertOk()
            ->assertSee('Gasóleo A')
            ->assertSee('1,600')
            ->assertSee('Sube');
    }

    public function test_la_pantalla_avisa_cuando_no_hay_datos(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('prices'))
            ->assertOk()
            ->assertSee('Todavía no hay precios sincronizados');
    }
}
