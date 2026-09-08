<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Distancias sobre la superficie terrestre.
 *
 * Vive aquí y no en un modelo porque la usan tanto una estación —que sabe
 * dónde está— como una zona de búsqueda, que no es un modelo de nada.
 */
final class Geo
{
    private const EARTH_RADIUS_KM = 6371.0;

    public static function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * asin(min(1.0, sqrt($a)));
    }
}
