<?php

declare(strict_types=1);

namespace App\Services\Geocoding;

use App\Support\Geo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Autocompletado de direcciones con Photon (OpenStreetMap), sin clave.
 *
 * Nunca se llama a Photon desde el navegador en cada tecla: la petición pasa
 * por el backend, que cachea. Las políticas de uso de los servicios OSM
 * gratuitos se saltan solas si se les manda una petición por pulsación.
 */
final class GeocodingClient
{
    private const BASE_URL = 'https://photon.komoot.io/api/';

    /** Centro aproximado de España para sesgar los resultados. */
    private const BIAS_LAT = 40.4;

    private const BIAS_LON = -3.7;

    /*
     * Caja que limita la búsqueda: península, Baleares, Canarias, Ceuta y
     * Melilla, con margen para Portugal, Andorra y el sur de Francia, que son
     * destinos plausibles de un viaje.
     *
     * Hace falta porque el sesgo por coordenadas de Photon es demasiado flojo.
     * Verificado contra la API real el 09-09-2026: buscando «malaga» devolvía
     * primero Malaga (California) y la de Andalucía en cuarto lugar.
     */
    private const BBOX = '-19.0,27.4,4.6,44.0';

    /**
     * Cuánto se agrupa la distancia al ordenar, en kilómetros.
     *
     * Sin agrupar, doscientos metros de diferencia reordenarían resultados que
     * Photon ya traía bien puestos. Agrupando, todo lo que está «igual de
     * cerca» conserva el orden que traía.
     */
    private const DISTANCE_BUCKET_KM = 25;

    /**
     * Busca sitios, poniendo delante los que caen cerca de quien pregunta.
     *
     * @return array<int, PlaceResult>
     */
    public function search(string $query, int $limit = 6, ?float $nearLat = null, ?float $nearLon = null): array
    {
        $query = trim($query);

        if (mb_strlen($query) < 3) {
            return [];
        }

        $near = $nearLat !== null && $nearLon !== null;

        /*
         * La v3 invalida lo cacheado con el orden anterior, que no miraba la
         * distancia.
         *
         * El punto entra en la clave porque el orden depende de él: si no,
         * quien busca desde Málaga se comería el orden de quien buscó desde
         * Madrid. Va redondeado a un decimal —unos once kilómetros— por dos
         * motivos: así la caché sirve para todo un barrio en vez de para una
         * sola persona, y así no queda la ubicación exacta de nadie escrita en
         * las claves.
         */
        $from = $near ? round($nearLat, 1).','.round($nearLon, 1) : 'es';
        $cacheKey = "geocode:v3:{$from}:".md5(mb_strtolower($query)).":{$limit}";

        if ($cached = Cache::get($cacheKey)) {
            return $this->hydrate($cached);
        }

        $response = Http::withHeaders([
            // Los servicios OSM exigen identificar la aplicación
            'User-Agent' => 'LibroDeTrayectos/1.0 (proyecto personal)',
        ])
            ->timeout(10)
            ->get(self::BASE_URL, [
                'q' => $query,
                'limit' => $limit,
                // Verificado el 2026-08-17: 'lang=es' devuelve 400. Con
                // 'default' los topónimos llegan ya en el idioma local, que
                // para España es exactamente lo que se quiere.
                'lang' => 'default',
                // Se le pasa igualmente, aunque su sesgo sea flojo: no estorba
                // y algo ayuda. El orden de verdad lo pone rank() más abajo.
                'lat' => $near ? $nearLat : self::BIAS_LAT,
                'lon' => $near ? $nearLon : self::BIAS_LON,
                'bbox' => self::BBOX,
            ]);

        if ($response->failed()) {
            Log::info('Photon no ha respondido', ['status' => $response->status()]);

            return [];
        }

        $payload = collect($response->json('features', []))
            ->values()
            // Se reordena antes de convertir, porque el criterio necesita los
            // campos crudos de Photon que PlaceResult ya no lleva.
            ->sortBy(fn (array $feature, int $position) => [
                $this->rank($feature, $query),
                $this->distanceBucket($feature, $nearLat, $nearLon),
                $position,
            ])
            ->map(fn (array $feature) => $this->toPlace($feature))
            ->filter()
            ->map(fn (PlaceResult $place) => $place->toArray())
            ->values()
            ->all();

        // Nunca cachear un resultado vacío: convertiría un fallo puntual del
        // servicio en un "no existe ese sitio" durante un mes.
        if ($payload !== []) {
            Cache::put($cacheKey, $payload, now()->addDays(30));
        }

        return $this->hydrate($payload);
    }

    /**
     * Orden propio, de menor a mayor: gana quien se llama exactamente como lo
     * buscado y, entre ésos, el sitio más grande.
     *
     * Photon ordena por su relevancia y pone un comercio por delante de una
     * capital de provincia: buscando «malaga» salía primero «Malaga 8 Guitar»,
     * una tienda de Madrid, y la ciudad en tercer lugar.
     *
     * Cuando no hay coincidencia exacta —una calle, un portal— todos empatan y
     * se conserva el orden de Photon, que para direcciones funciona bien.
     */
    private function rank(array $feature, string $query): int
    {
        $properties = $feature['properties'] ?? [];

        $exact = $this->normalize((string) ($properties['name'] ?? '')) === $this->normalize($query);

        $size = match ($properties['type'] ?? '') {
            'city' => 0,
            'county', 'state' => 1,
            'district', 'locality' => 2,
            'street' => 3,
            default => 4,   // portales, comercios y demás
        };

        return ($exact ? 0 : 10) + $size;
    }

    /**
     * A qué distancia cae, en grupos de veinticinco kilómetros.
     *
     * Es el desempate: entre dos resultados que valen lo mismo —dos calles con
     * el mismo nombre, por ejemplo— gana la de al lado. Buscando «Calle
     * Larios» desde Málaga salía primero una de Toledo, porque para Photon las
     * dos son igual de buenas y no sabe desde dónde se pregunta.
     *
     * No puede ir ANTES del criterio de rank(): si mandara la distancia,
     * buscando «Madrid» desde Málaga saldría antes una calle Madrid de aquí al
     * lado que la ciudad de Madrid.
     */
    private function distanceBucket(array $feature, ?float $nearLat, ?float $nearLon): int
    {
        $coordinates = data_get($feature, 'geometry.coordinates');

        if ($nearLat === null || $nearLon === null || ! is_array($coordinates) || count($coordinates) < 2) {
            return 0;   // Sin punto de referencia todos empatan y manda Photon
        }

        // Photon los da en [lon, lat]
        $km = Geo::haversineKm($nearLat, $nearLon, (float) $coordinates[1], (float) $coordinates[0]);

        return (int) floor($km / self::DISTANCE_BUCKET_KM);
    }

    private function normalize(string $value): string
    {
        return Str::lower(Str::ascii(trim($value)));
    }

    /** @return array<int, PlaceResult> */
    private function hydrate(array $rows): array
    {
        return array_map(
            fn (array $row) => new PlaceResult($row['label'], $row['lat'], $row['lon'], $row['context']),
            $rows
        );
    }

    private function toPlace(array $feature): ?PlaceResult
    {
        $coordinates = data_get($feature, 'geometry.coordinates');

        if (! is_array($coordinates) || count($coordinates) < 2) {
            return null;
        }

        $properties = $feature['properties'] ?? [];

        $name = $properties['name']
            ?? trim(($properties['street'] ?? '').' '.($properties['housenumber'] ?? ''))
            ?: ($properties['city'] ?? null);

        if (blank($name)) {
            return null;
        }

        $context = collect([
            $properties['city'] ?? null,
            $properties['state'] ?? null,
            $properties['country'] ?? null,
        ])->filter()->unique()->implode(', ');

        return new PlaceResult(
            label: (string) $name,
            lat: (float) $coordinates[1],
            lon: (float) $coordinates[0],
            context: $context ?: null,
        );
    }
}
