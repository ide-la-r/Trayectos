<?php

declare(strict_types=1);

namespace App\Services\Routing;

use App\Services\Support\ApiQuota;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Cliente de OpenRouteService (HeiGIT).
 *
 * Con elevation=true una sola llamada devuelve distancia, duración, ascenso y
 * descenso acumulados y la geometría en 3D. Es la razón por la que no hace
 * falta gastar cuota en un servicio de altitud aparte para el caso normal.
 */
final class OpenRouteServiceClient
{
    public function __construct(private readonly ApiQuota $quota) {}

    public function isConfigured(): bool
    {
        return filled(config('trayectos.ors.key'));
    }

    public function quota(): ApiQuota
    {
        return $this->quota;
    }

    /**
     * @throws RuntimeException cuando ORS no responde o no hay ruta
     */
    public function route(float $originLat, float $originLon, float $destLat, float $destLon): RouteResult
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Falta ORS_API_KEY.');
        }

        if (! $this->quota->hasRoom()) {
            throw new RuntimeException('Cuota diaria de OpenRouteService agotada.');
        }

        $cacheKey = $this->cacheKey($originLat, $originLon, $destLat, $destLon);

        if ($cached = Cache::get($cacheKey)) {
            return $this->hydrate($cached);
        }

        $profile = config('trayectos.ors.profile');

        $response = Http::withHeaders([
            'Authorization' => config('trayectos.ors.key'),
            'Content-Type' => 'application/json; charset=utf-8',
        ])
            ->timeout((int) config('trayectos.ors.timeout'))
            ->retry(2, 500, throw: false)
            ->post(rtrim((string) config('trayectos.ors.base_url'), '/')."/v2/directions/{$profile}/geojson", [
                'coordinates' => [
                    [$originLon, $originLat],   // ORS espera [lon, lat]
                    [$destLon, $destLat],
                ],
                'elevation' => true,
                'instructions' => false,
                'units' => 'm',
            ]);

        $this->quota->consume();

        if ($response->failed()) {
            Log::warning('ORS ha devuelto un error', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            throw new RuntimeException("OpenRouteService ha respondido {$response->status()}.");
        }

        $feature = $response->json('features.0');

        if (! $feature) {
            throw new RuntimeException('OpenRouteService no ha encontrado ruta entre esos puntos.');
        }

        $payload = [
            'distance_m' => (int) round((float) data_get($feature, 'properties.summary.distance', 0)),
            'duration_s' => (int) round((float) data_get($feature, 'properties.summary.duration', 0)),
            'ascent_m' => (int) round((float) data_get($feature, 'properties.ascent', 0)),
            'descent_m' => (int) round((float) data_get($feature, 'properties.descent', 0)),
            'geometry' => $this->simplifyGeometry(data_get($feature, 'geometry.coordinates', [])),
        ];

        // Las rutas entre los mismos dos puntos no cambian: cachear una semana
        // ahorra la mayor parte de la cuota en un grupo con trayectos habituales.
        Cache::put($cacheKey, $payload, now()->addWeek());

        return $this->hydrate($payload);
    }

    private function hydrate(array $payload): RouteResult
    {
        return new RouteResult(
            distanceM: $payload['distance_m'],
            ascentM: $payload['ascent_m'],
            descentM: $payload['descent_m'],
            source: 'ors',
            durationS: $payload['duration_s'],
            geometry: $payload['geometry'],
        );
    }

    /** Guardar 4.000 vértices por viaje no aporta nada: uno de cada N basta. */
    private function simplifyGeometry(array $coordinates, int $maxPoints = 120): array
    {
        $count = count($coordinates);

        if ($count <= $maxPoints) {
            return $coordinates;
        }

        $step = (int) ceil($count / $maxPoints);
        $simplified = [];

        for ($i = 0; $i < $count; $i += $step) {
            $simplified[] = $coordinates[$i];
        }

        $simplified[] = $coordinates[$count - 1];

        return $simplified;
    }

    private function cacheKey(float $oLat, float $oLon, float $dLat, float $dLon): string
    {
        // ~11 m de resolución: suficiente para considerar que es "la misma ruta"
        return 'ors:route:'.implode(':', array_map(
            fn (float $value) => number_format($value, 4, '.', ''),
            [$oLat, $oLon, $dLat, $dLon]
        ));
    }
}
