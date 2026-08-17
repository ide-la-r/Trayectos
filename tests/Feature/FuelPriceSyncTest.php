<?php

namespace Tests\Feature;

use App\Enums\FuelKind;
use App\Models\FuelPrice;
use App\Models\FuelStation;
use App\Services\Fuel\FuelPriceResolver;
use App\Services\Fuel\FuelPriceSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FuelPriceSyncTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Respuesta real del Ministerio (recortada), tal cual la devuelve el
     * endpoint FiltroProvincia: precios con coma decimal, cadena vacía cuando
     * la estación no vende ese producto y nombres de campo con tildes.
     */
    private function mitecoResponse(): array
    {
        return [
            'Fecha' => '17/08/2026 17:44:12',
            'ListaEESSPrecio' => [
                [
                    'C.P.' => '05296',
                    'Dirección' => 'AUTOVIA NOROESTE KM. 111',
                    'Horario' => 'L-D: 24H',
                    'Latitud' => '40,955111',
                    'Longitud (WGS84)' => '-4,614556',
                    'Localidad' => 'ADANERO',
                    'Municipio' => 'Adanero',
                    'Provincia' => 'ÁVILA',
                    'Precio Gasoleo A' => '1,974',
                    'Precio Gasolina 95 E5' => '1,759',
                    'Precio Gasolina 98 E5' => '1,919',
                    'Precio Gases licuados del petróleo' => '',
                    'Precio Gas Natural Comprimido' => '',
                    'Rótulo' => 'CEPSA',
                    'IDEESS' => '4982',
                    'IDProvincia' => '05',
                ],
                [
                    'C.P.' => '05200',
                    'Dirección' => 'CARRETERA N-VI KM 100',
                    'Latitud' => '40,900000',
                    'Longitud (WGS84)' => '-4,700000',
                    'Municipio' => 'Arévalo',
                    'Provincia' => 'ÁVILA',
                    'Precio Gasoleo A' => '1,899',
                    'Precio Gasolina 95 E5' => '1,689',
                    'Rótulo' => 'REPSOL',
                    'IDEESS' => '4983',
                    'IDProvincia' => '05',
                ],
            ],
        ];
    }

    public function test_it_imports_stations_and_prices_from_the_ministry(): void
    {
        Http::fake(['*minetur*' => Http::response($this->mitecoResponse())]);

        $result = app(FuelPriceSynchronizer::class)->sync(['05']);

        $this->assertSame(2, $result['stations']);
        $this->assertSame(5, $result['prices']);   // los vacíos no se importan
        $this->assertSame('17/08/2026 17:44', $result['observed_at']->format('d/m/Y H:i'));

        $station = FuelStation::where('ideess', 4982)->firstOrFail();
        $this->assertSame('CEPSA', $station->label);
        $this->assertEqualsWithDelta(40.955111, $station->lat, 0.000001);
        $this->assertEqualsWithDelta(-4.614556, $station->lon, 0.000001);

        // '1,974' € => 1974 milésimas, sin pasar nunca por un float de dinero
        $this->assertSame(1974, FuelPrice::where('fuel_station_id', $station->id)
            ->where('fuel_kind', FuelKind::Diesel)->value('price_milli'));
    }

    public function test_syncing_twice_does_not_duplicate_anything(): void
    {
        Http::fake(['*minetur*' => Http::response($this->mitecoResponse())]);

        app(FuelPriceSynchronizer::class)->sync(['05']);
        app(FuelPriceSynchronizer::class)->sync(['05']);

        $this->assertSame(2, FuelStation::count());
        $this->assertSame(5, FuelPrice::count());
    }

    public function test_the_resolver_picks_the_cheapest_station_nearby(): void
    {
        Http::fake(['*minetur*' => Http::response($this->mitecoResponse())]);
        app(FuelPriceSynchronizer::class)->sync(['05']);

        $price = app(FuelPriceResolver::class)->resolve(FuelKind::Gasoline95, 40.95, -4.61);

        $this->assertSame('station', $price->source);
        $this->assertSame(1689, $price->priceMilli);   // la de Arévalo es más barata
        $this->assertStringContainsString('REPSOL', $price->stationLabel);
    }

    public function test_the_resolver_uses_the_national_average_when_far_away(): void
    {
        Http::fake(['*minetur*' => Http::response($this->mitecoResponse())]);
        app(FuelPriceSynchronizer::class)->sync(['05']);

        // Canarias: no hay ninguna estación importada cerca
        $price = app(FuelPriceResolver::class)->resolve(FuelKind::Gasoline95, 28.1, -15.4);

        $this->assertSame('national_average', $price->source);
        $this->assertSame(1724, $price->priceMilli);   // media de 1759 y 1689
    }

    public function test_the_resolver_falls_back_when_there_is_no_data_at_all(): void
    {
        $price = app(FuelPriceResolver::class)->resolve(FuelKind::Diesel, 40.4, -3.7);

        $this->assertSame('fallback', $price->source);
        $this->assertFalse($price->isReal());
        $this->assertSame(config('trayectos.fallback_prices.fuel_milli.GOA'), $price->priceMilli);
    }
}
