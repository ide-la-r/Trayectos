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

    /** Málaga capital y Madrid capital, que es el par que se repite. */
    private const MALAGA = [36.7213, -4.4214];

    private const MADRID = [40.4168, -3.7038];

    private function buscar(string $query, ?array $desde = null): array
    {
        return app(GeocodingClient::class)->search(
            $query,
            nearLat: $desde[0] ?? null,
            nearLon: $desde[1] ?? null,
        );
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

    public function test_entre_dos_iguales_gana_la_que_cae_cerca(): void
    {
        /*
         * El caso que lo motivó: buscando «Calle Larios» desde Málaga salía
         * primero una de Navamorcuende, en Toledo. Para el buscador las dos
         * valen lo mismo; lo que le faltaba era saber desde dónde se pregunta.
         */
        $this->photonDevuelve([
            $this->sitio('Calle Larios', 'street', 40.15, -4.68),   // Toledo
            $this->sitio('Calle Larios', 'street', 36.72, -4.42),   // Málaga
        ]);

        $this->assertSame(36.72, round($this->buscar('calle larios', self::MALAGA)[0]->lat, 2));

        // Y desde Madrid, la de Toledo, que es la que le pilla cerca
        $this->assertSame(40.15, round($this->buscar('calle larios', self::MADRID)[0]->lat, 2));
    }

    public function test_lo_cercano_no_puede_ganarle_a_lo_que_se_llama_igual(): void
    {
        /*
         * La distancia es SÓLO el desempate. Si mandara ella, buscando
         * «Madrid» desde Málaga saldría antes una calle Madrid de aquí al lado
         * que la ciudad de Madrid, y eso sería peor que el problema original.
         */
        $this->photonDevuelve([
            $this->sitio('Calle Madrid', 'street', 36.72, -4.42),   // A la vuelta de la esquina
            $this->sitio('Madrid', 'city', 40.41, -3.70),           // A 500 km
        ]);

        $this->assertSame('Madrid', $this->buscar('madrid', self::MALAGA)[0]->label);
    }

    public function test_sin_saber_desde_donde_se_busca_no_se_reordena_nada(): void
    {
        // Quien no ha elegido zona ni tiene viajes: se respeta lo que traiga
        $this->photonDevuelve([
            $this->sitio('Calle Larios', 'street', 40.15, -4.68),
            $this->sitio('Calle Larios', 'street', 36.72, -4.42),
        ]);

        $this->assertSame(40.15, round($this->buscar('calle larios')[0]->lat, 2));
    }

    public function test_el_punto_desde_el_que_se_busca_va_en_la_clave_de_la_cache(): void
    {
        /*
         * Sin esto, quien busca desde Málaga se comería el orden de quien
         * buscó lo mismo desde Madrid, y el arreglo entero se quedaría en
         * nada durante los treinta días que dura la caché.
         */
        $this->photonDevuelve([$this->sitio('Calle Larios', 'street')]);

        $this->buscar('calle larios', self::MALAGA);
        $this->buscar('calle larios', self::MADRID);

        Http::assertSentCount(2);
    }

    public function test_a_photon_se_le_dice_desde_donde_se_pregunta(): void
    {
        $this->photonDevuelve([]);

        $this->buscar('lo que sea', self::MALAGA);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'lat=36.7213')
            && str_contains($request->url(), 'lon=-4.4214'));
    }

    public function test_la_busqueda_se_limita_a_la_caja_de_iberia(): void
    {
        $this->photonDevuelve([]);

        $this->buscar('un sitio que no existe');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'bbox=')
            && str_contains(urldecode($request->url()), '-19.0,27.4,4.6,44.0'));
    }
}
