<?php

declare(strict_types=1);

namespace App\Services\Trips;

use App\Models\Trip;
use App\Support\Geo;
use Illuminate\Support\Collection;

/**
 * Perfil de altitud de un trayecto, listo para dibujar.
 *
 * No hay que pedir nada a nadie: OpenRouteService devuelve la geometría con la
 * altitud en el tercer valor de cada punto y el viaje la guarda entera. Igual
 * que con el histórico de precios, el dato ya estaba y nadie lo miraba.
 *
 * Sólo hay geometría cuando ORS resolvió la ruta. Si se degradó a línea recta
 * el trayecto tiene desnivel —muestreado aparte— pero no recorrido, así que
 * aquí no hay nada que dibujar y se devuelve null.
 */
final class RouteProfiler
{
    /**
     * @return object{
     *     points: Collection,
     *     distance_km: float,
     *     round_trip: bool,
     *     min_m: int,
     *     max_m: int,
     *     start_m: int,
     *     end_m: int,
     *     peak: object
     * }|null
     */
    public function profileFor(Trip $trip): ?object
    {
        $geometry = $trip->route_geometry;

        if (! is_array($geometry) || count($geometry) < 2) {
            return null;
        }

        $points = collect();
        $distanceKm = 0.0;
        $previous = null;

        foreach ($geometry as $vertex) {
            // [lon, lat, altitud]. Sin el tercero no hay perfil: pasa cuando la
            // ruta se guardó antes de pedirle elevación a ORS.
            if (! is_array($vertex) || count($vertex) < 3) {
                return null;
            }

            $lon = (float) $vertex[0];
            $lat = (float) $vertex[1];

            if ($previous !== null) {
                $distanceKm += Geo::haversineKm($previous[0], $previous[1], $lat, $lon);
            }

            $points->push((object) [
                'km' => round($distanceKm, 3),
                'm' => (int) round((float) $vertex[2]),
            ]);

            $previous = [$lat, $lon];
        }

        /*
         * Los kilómetros del dibujo se escalan a la distancia real, por dos
         * motivos que se suman:
         *
         *   - La geometría está diezmada a ~120 puntos, así que sumar sus
         *     tramos se queda corto respecto de la ruta de verdad.
         *   - En ida y vuelta trips.distance_m viene doblado, pero la
         *     geometría es sólo la ida.
         *
         * Sin esto la pantalla enseñaría dos distancias distintas para el
         * mismo viaje: la de la tarjeta de arriba y la del perfil.
         */
        $oneWayKm = ($trip->round_trip ? $trip->distance_m / 2 : $trip->distance_m) / 1000;
        $factor = ($distanceKm > 0 && $oneWayKm > 0) ? $oneWayKm / $distanceKm : 1.0;

        $points = $points->map(fn (object $p) => (object) [
            'km' => round($p->km * $factor, 3),
            'm' => $p->m,
        ]);

        $altitudes = $points->pluck('m');

        return (object) [
            'points' => $points,
            'distance_km' => round($distanceKm * $factor, 1),
            'round_trip' => (bool) $trip->round_trip,
            'min_m' => (int) $altitudes->min(),
            'max_m' => (int) $altitudes->max(),
            'start_m' => (int) $points->first()->m,
            'end_m' => (int) $points->last()->m,
            'peak' => $points->sortByDesc('m')->first(),
        ];
    }
}
