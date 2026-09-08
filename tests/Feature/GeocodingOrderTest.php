<?php

namespace Tests\Feature;

use App\Services\Geocoding\GeocodingClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * El orden del buscador de sitios.
 *
 * Photon ordena por su propia relevancia y eso daba resultados absurdos:
 * verificado contra la API real el 09-09-2026, buscar «malaga» devolvía
 * primero Malaga (California) y, quitando ésa con la caja de Iberia, una
 * tienda de guitarras de Madrid por delante de la capital de provincia.
 */
class GeocodingOrderTest extends TestCase
{
    /** @param array<string, mixed> $extra */
    private function sitio(string $name, string $type, float $lat = 36.72, float $lon = -4.42, array $extra = []): array
    {
        return [
            'geometry' => ['coordinates' => [$lon, $lat]],
            'properties' => array_merge([
                'name' => $name,
                'type' => $type,
                'country' => 'España',
            ], $extra),
        ];
    }

    private function photonDevuelve(array $sitios): void
    {
        Http::fake(['*photon*' => Http::response(['features' => $sitios])]);
    }

    private function buscar(string $query): array
    {
        return app(GeocodingClient::class)->search($query);
    }

    public function test_una_ciudad_gana_a_un_comercio_que_se_llama_parecido(): void
    {
        $this->photonDevuelve([
            $this->sitio('Malaga 8 Guitar', 'house', 40.42, -3.70),
            $this->sitio('Malagana', 'locality', 40.30, -3.60),
            $this->sitio('Málaga', 'city'),
        ]);

        $this->assertSame('Málaga', $this->buscar('malaga')[0]->label);
    }

    public function test_la_tilde_no_impide_la_coincidencia_exacta(): void
    {
        // Se escribe «malaga» sin tilde, que es como se escribe en un móvil
        $this->photonDevuelve([
            $this->sitio('Malaga Beach Bar', 'house', 40.42, -3.70),
            $this->sitio('Málaga', 'city'),
        ]);

        $this->assertSame('Málaga', $this->buscar('MALAGA')[0]->label);
    }

    public function test_entre_dos_coincidencias_exactas_gana_la_mas_grande(): void
    {
        // Las dos se llaman «Granada»; se distinguen por dónde caen
        $this->photonDevuelve([
            $this->sitio('Granada', 'county', 37.50, -3.30),
            $this->sitio('Granada', 'city', 37.18, -3.60),
        ]);

        $resultados = $this->buscar('granada');

        // La ciudad antes que la provincia: es lo que se busca al escribirla
        $this->assertSame(37.18, round($resultados[0]->lat, 2));
    }

    public function test_sin_coincidencia_exacta_manda_el_orden_de_photon(): void
    {
        // Una dirección: ninguno se llama como lo buscado, así que todos
        // empatan y no se toca el orden, que para direcciones ya es bueno.
        $this->photonDevuelve([
            $this->sitio('Calle Larios 5', 'house'),
            $this->sitio('Calle Larios 7', 'house'),
        ]);

        $this->assertSame('Calle Larios 5', $this->buscar('calle larios')[0]->label);
    }

    public function test_la_busqueda_se_limita_a_la_caja_de_iberia(): void
    {
        $this->photonDevuelve([]);

        $this->buscar('un sitio que no existe');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'bbox=')
            && str_contains(urldecode($request->url()), '-19.0,27.4,4.6,44.0'));
    }
}
