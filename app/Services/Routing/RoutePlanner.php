<?php

declare(strict_types=1);

namespace App\Services\Routing;

use App\Services\Elevation\OpenTopoDataClient;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orquesta el cálculo de una ruta con degradación en cascada:
 *
 *   1. OpenRouteService con elevación (distancia y desnivel reales).
 *   2. Si ORS falla o se agotó la cuota: distancia en línea recta corregida por
 *      un factor de sinuosidad, y desnivel muestreado en Open Topo Data.
 *   3. Si tampoco hay altitud: distancia estimada y desnivel cero, avisando.
 *
 * Que se agote una cuota gratuita no puede impedir apuntar un viaje. Como
 * mucho, debe bajar la precisión y decirlo.
 */
final class RoutePlanner
{
    /** Relación media entre distancia por carretera y línea recta en España. */
    private const ROAD_WINDING_FACTOR = 1.25;

    public function __construct(
        private readonly OpenRouteServiceClient $ors,
        private readonly OpenTopoDataClient $elevation,
    ) {}

    public function plan(float $originLat, float $originLon, float $destLat, float $destLon): RouteResult
    {
        if ($this->ors->isConfigured()) {
            try {
                return $this->ors->route($originLat, $originLon, $destLat, $destLon);
            } catch (Throwable $exception) {
                Log::info('Ruta degradada a estimación', ['motivo' => $exception->getMessage()]);
            }
        }

        return $this->estimate($originLat, $originLon, $destLat, $destLon);
    }

    /** Ruta estimada sin ORS: línea recta corregida + altitud muestreada. */
    public function estimate(float $originLat, float $originLon, float $destLat, float $destLon): RouteResult
    {
        $straightM = $this->haversineMetres($originLat, $originLon, $destLat, $destLon);
        $distanceM = (int) round($straightM * self::ROAD_WINDING_FACTOR);

        $profile = $this->elevation->profileFor(
            $this->interpolate($originLat, $originLon, $destLat, $destLon)
        );

        return new RouteResult(
            distanceM: $distanceM,
            ascentM: $profile['ascent_m'] ?? 0,
            descentM: $profile['descent_m'] ?? 0,
            source: 'haversine',
            durationS: null,
            geometry: null,
            warning: $profile === null
                ? 'Distancia estimada en línea recta y sin datos de desnivel. Ajusta los valores a mano si los conoces.'
                : 'Distancia estimada en línea recta (el desnivel sí es real).',
        );
    }

    public function haversineMetres(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusM = 6_371_000;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earthRadiusM * 2 * asin(min(1.0, sqrt($a)));
    }

    /**
     * Puntos equiespaciados sobre la recta origen-destino para pedir altitudes.
     *
     * @return array<int, array{0: float, 1: float}>
     */
    private function interpolate(float $lat1, float $lon1, float $lat2, float $lon2, int $samples = 60): array
    {
        $points = [];

        for ($i = 0; $i < $samples; $i++) {
            $t = $i / ($samples - 1);
            $points[] = [
                $lat1 + ($lat2 - $lat1) * $t,
                $lon1 + ($lon2 - $lon1) * $t,
            ];
        }

        return $points;
    }
}
