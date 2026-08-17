<?php

declare(strict_types=1);

namespace App\Services\Fuel;

use App\Enums\FuelKind;
use App\Models\FuelPrice;
use App\Models\FuelStation;
use Illuminate\Support\Carbon;

/**
 * Elige qué precio se aplica a un trayecto, en cascada:
 *   1. La gasolinera más barata dentro del radio del punto de partida.
 *   2. La media nacional de la última observación oficial.
 *   3. El precio de respaldo de configuración, marcado como tal.
 *
 * El precio elegido se congela en el snapshot del viaje: si mañana sube el
 * gasóleo, el viaje de ayer sigue costando lo que costó.
 */
final class FuelPriceResolver
{
    public function resolve(FuelKind $kind, ?float $lat = null, ?float $lon = null, float $radiusKm = 25): ResolvedPrice
    {
        if ($kind === FuelKind::None) {
            return new ResolvedPrice(0, 'fallback');
        }

        $latestObservation = FuelPrice::where('fuel_kind', $kind)->max('observed_at');

        if ($latestObservation && $lat !== null && $lon !== null) {
            if ($nearby = $this->cheapestNearby($kind, $latestObservation, $lat, $lon, $radiusKm)) {
                return $nearby;
            }
        }

        if ($latestObservation) {
            $average = FuelPrice::where('fuel_kind', $kind)
                ->where('observed_at', $latestObservation)
                ->avg('price_milli');

            if ($average) {
                return new ResolvedPrice(
                    priceMilli: (int) round($average),
                    source: 'national_average',
                    observedAt: Carbon::parse($latestObservation),
                );
            }
        }

        return new ResolvedPrice($kind->fallbackPriceMilli(), 'fallback');
    }

    private function cheapestNearby(
        FuelKind $kind,
        string $observedAt,
        float $lat,
        float $lon,
        float $radiusKm,
    ): ?ResolvedPrice {
        $stationIds = FuelStation::near($lat, $lon, $radiusKm)->pluck('id');

        if ($stationIds->isEmpty()) {
            return null;
        }

        $price = FuelPrice::with('station')
            ->where('fuel_kind', $kind)
            ->where('observed_at', $observedAt)
            ->whereIn('fuel_station_id', $stationIds)
            ->orderBy('price_milli')
            ->first();

        if (! $price) {
            return null;
        }

        return new ResolvedPrice(
            priceMilli: $price->price_milli,
            source: 'station',
            stationId: $price->fuel_station_id,
            stationLabel: trim(($price->station->label ?? 'Gasolinera').' · '.($price->station->municipality ?? '')),
            observedAt: $price->observed_at,
            distanceKm: round($price->station->distanceKmTo($lat, $lon), 1),
        );
    }
}
