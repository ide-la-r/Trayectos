<?php

declare(strict_types=1);

namespace App\Services\Elevation;

use App\Services\Support\ApiQuota;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Perfil de altitud desde la API pública de Open Topo Data.
 *
 * Sin clave y sin tarjeta, a cambio de límites estrictos: 100 puntos por
 * petición, 1 llamada por segundo y 1.000 al día. Se usa como respaldo cuando
 * ORS no está disponible, o para recalcular un perfil con un modelo digital
 * más fino que el de ORS (eudem25m cubre España a 25 m).
 */
final class OpenTopoDataClient
{
    public function __construct(private readonly ApiQuota $quota) {}

    /**
     * Ascenso y descenso acumulados de una polilínea.
     *
     * @param  array<int, array{0: float, 1: float}>  $points  [[lat, lon], …]
     * @return array{ascent_m: int, descent_m: int, samples: int}|null
     */
    public function profileFor(array $points): ?array
    {
        $points = $this->sample($points, (int) config('trayectos.opentopodata.max_points_per_request'));

        if (count($points) < 2) {
            return null;
        }

        $cacheKey = 'otd:profile:'.md5(json_encode($points));

        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        if (! $this->quota->hasRoom()) {
            Log::info('Cuota diaria de Open Topo Data agotada.');

            return null;
        }

        $dataset = config('trayectos.opentopodata.dataset');
        $locations = implode('|', array_map(
            fn (array $point) => number_format($point[0], 6, '.', '').','.number_format($point[1], 6, '.', ''),
            $points
        ));

        $response = Http::timeout((int) config('trayectos.opentopodata.timeout'))
            ->retry(2, 1200, throw: false)
            ->get(rtrim((string) config('trayectos.opentopodata.base_url'), '/')."/v1/{$dataset}", [
                'locations' => $locations,
            ]);

        $this->quota->consume();

        if ($response->failed() || $response->json('status') !== 'OK') {
            Log::warning('Open Topo Data no ha respondido correctamente', [
                'status' => $response->status(),
            ]);

            return null;
        }

        $elevations = collect($response->json('results', []))
            ->pluck('elevation')
            ->filter(fn ($value) => $value !== null)
            ->map(fn ($value) => (float) $value)
            ->values();

        if ($elevations->count() < 2) {
            return null;
        }

        $profile = $this->accumulate($elevations->all());

        Cache::put($cacheKey, $profile, now()->addMonth());

        return $profile;
    }

    /**
     * Ascenso y descenso acumulados con un umbral de ruido: los modelos
     * digitales tienen error de varios metros y sin filtro un tramo llano
     * acumularía cientos de metros de "desnivel" inexistente.
     */
    private function accumulate(array $elevations, float $noiseThresholdM = 3.0): array
    {
        $ascent = 0.0;
        $descent = 0.0;
        $reference = $elevations[0];

        foreach ($elevations as $elevation) {
            $delta = $elevation - $reference;

            if (abs($delta) < $noiseThresholdM) {
                continue;
            }

            $delta > 0 ? $ascent += $delta : $descent += abs($delta);
            $reference = $elevation;
        }

        return [
            'ascent_m' => (int) round($ascent),
            'descent_m' => (int) round($descent),
            'samples' => count($elevations),
        ];
    }

    /** @param array<int, array{0: float, 1: float}> $points */
    private function sample(array $points, int $max): array
    {
        $count = count($points);

        if ($count <= $max) {
            return $points;
        }

        $sampled = [];
        $step = ($count - 1) / ($max - 1);

        for ($i = 0; $i < $max; $i++) {
            $sampled[] = $points[(int) round($i * $step)];
        }

        return $sampled;
    }
}
