<?php

namespace Tests\Feature;

use App\Enums\FuelKind;
use App\Models\FuelPrice;
use App\Models\FuelStation;
use App\Services\Fuel\FuelDataPruner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * La copia local tiene que quedarse acotada.
 *
 * Nada borraba nunca: con cuatro sincronizaciones al día, fuel_prices crecía
 * sin techo contra los 500 MB del plan gratuito. Y las estaciones de una
 * provincia que sale de la configuración se quedaban para siempre, que es lo
 * que hacía que a alguien de Málaga le saliera Móstoles.
 */
class FuelDataPruneTest extends TestCase
{
    use RefreshDatabase;

    private function estacion(string $provinciaId, ?string $label = null): FuelStation
    {
        static $ideess = 7000;

        return FuelStation::create([
            'ideess' => $ideess++,
            'label' => $label ?? 'Estación '.$provinciaId,
            'municipality' => 'Municipio',
            'province_id' => $provinciaId,
            'lat' => 36.7,
            'lon' => -4.4,
        ]);
    }

    private function precio(FuelStation $estacion, string $cuando): FuelPrice
    {
        return FuelPrice::create([
            'fuel_station_id' => $estacion->id,
            'fuel_kind' => FuelKind::Diesel->value,
            'price_milli' => 1500,
            'observed_at' => $cuando,
        ]);
    }

    private function pruner(): FuelDataPruner
    {
        return app(FuelDataPruner::class);
    }

    public function test_borra_las_estaciones_de_provincias_que_ya_no_se_sincronizan(): void
    {
        config(['trayectos.miteco.provinces' => ['29']]);

        $malaga = $this->estacion('29');
        $madrid = $this->estacion('28');

        $this->precio($malaga, now()->toDateTimeString());
        $this->precio($madrid, now()->toDateTimeString());

        $resultado = $this->pruner()->prune();

        $this->assertSame(1, $resultado['stations']);
        $this->assertDatabaseHas('fuel_stations', ['id' => $malaga->id]);
        $this->assertDatabaseMissing('fuel_stations', ['id' => $madrid->id]);

        // Y sus precios se van con ellas, sin dejar filas huérfanas
        $this->assertDatabaseHas('fuel_prices', ['fuel_station_id' => $malaga->id]);
        $this->assertDatabaseMissing('fuel_prices', ['fuel_station_id' => $madrid->id]);
    }

    public function test_una_estacion_sin_provincia_no_se_toca(): void
    {
        config(['trayectos.miteco.provinces' => ['29']]);

        $huerfana = FuelStation::create([
            'ideess' => 7900,
            'label' => 'Sin provincia',
            'lat' => 36.7,
            'lon' => -4.4,
        ]);

        $this->pruner()->prune();

        // No sabemos de dónde es: borrarla sería decidir por suposición
        $this->assertDatabaseHas('fuel_stations', ['id' => $huerfana->id]);
    }

    public function test_en_modo_nacional_no_sobra_ninguna_provincia(): void
    {
        config(['trayectos.miteco.provinces' => []]);

        $madrid = $this->estacion('28');
        $sevilla = $this->estacion('41');

        $resultado = $this->pruner()->prune();

        $this->assertSame(0, $resultado['stations']);
        $this->assertDatabaseHas('fuel_stations', ['id' => $madrid->id]);
        $this->assertDatabaseHas('fuel_stations', ['id' => $sevilla->id]);
    }

    public function test_borra_las_observaciones_viejas_y_conserva_las_recientes(): void
    {
        config(['trayectos.miteco.provinces' => ['29']]);

        $estacion = $this->estacion('29');

        $vieja = $this->precio($estacion, now()->subDays(FuelDataPruner::KEEP_DAYS + 5)->toDateTimeString());
        $reciente = $this->precio($estacion, now()->subDays(FuelDataPruner::KEEP_DAYS - 5)->toDateTimeString());

        $resultado = $this->pruner()->prune();

        $this->assertSame(1, $resultado['prices']);
        $this->assertDatabaseMissing('fuel_prices', ['id' => $vieja->id]);
        $this->assertDatabaseHas('fuel_prices', ['id' => $reciente->id]);
    }

    public function test_la_sincronizacion_normal_limpia_al_terminar(): void
    {
        config(['trayectos.miteco.provinces' => ['29']]);

        $madrid = $this->estacion('28');

        // El sincronizador es final y Mockery no puede sustituirlo, así que se
        // finge la respuesta del Ministerio: vacía, que aquí no importa.
        Http::fake(['*ServiciosRESTCarburantes*' => Http::response([
            'Fecha' => now()->format('d/m/Y H:i:s'),
            'ListaEESSPrecio' => [],
        ])]);

        $this->artisan('trayectos:sync-prices', ['--skip-energy' => true])
            ->assertSuccessful();

        $this->assertDatabaseMissing('fuel_stations', ['id' => $madrid->id]);
    }

    public function test_una_sincronizacion_a_medida_no_limpia_nada(): void
    {
        config(['trayectos.miteco.provinces' => ['29']]);

        $madrid = $this->estacion('28');

        // El sincronizador es final y Mockery no puede sustituirlo, así que se
        // finge la respuesta del Ministerio: vacía, que aquí no importa.
        Http::fake(['*ServiciosRESTCarburantes*' => Http::response([
            'Fecha' => now()->format('d/m/Y H:i:s'),
            'ListaEESSPrecio' => [],
        ])]);

        // Pedir «solo Madrid» de una vez y que la limpieza lo borre acto
        // seguido sería sorprendente: la limpieza usa siempre la configuración.
        $this->artisan('trayectos:sync-prices', ['--provinces' => '28', '--skip-energy' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('fuel_stations', ['id' => $madrid->id]);
    }
}
