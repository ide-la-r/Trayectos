<?php

declare(strict_types=1);

namespace App\Services\Trips;

use App\Services\Costing\TripCostCalculator;
use App\Services\Costing\TripCostRequest;
use App\Services\Energy\EnergyPriceResolver;
use App\Services\Fuel\FuelPriceResolver;
use App\Services\Routing\RoutePlanner;
use App\Services\Routing\RouteResult;

/**
 * Calcula ruta, precios y coste de un trayecto sin guardar nada. Lo usan tanto
 * la previsualización del formulario como el registro definitivo, para que lo
 * que el usuario ve antes de guardar sea exactamente lo que se guarda.
 */
final class TripEstimator
{
    public function __construct(
        private readonly RoutePlanner $planner,
        private readonly FuelPriceResolver $fuelPrices,
        private readonly EnergyPriceResolver $energyPrices,
        private readonly TripCostCalculator $calculator,
    ) {}

    public function estimate(TripDraft $draft): TripEstimate
    {
        $route = $this->resolveRoute($draft);

        $fuelPrice = $this->fuelPrices->resolve(
            $draft->vehicle->fuel_kind,
            $draft->originLat,
            $draft->originLon,
        );

        $energyPrice = $this->energyPrices->resolve();

        $breakdown = $this->calculator->calculate(new TripCostRequest(
            vehicle: $draft->vehicle,
            distanceM: $route->distanceM,
            ascentM: $route->ascentM,
            descentM: $route->descentM,
            occupants: $draft->occupants(),
            fuelPriceMilli: $fuelPrice->priceMilli,
            energyPriceMilli: $energyPrice->priceMilli,
            luggageKg: $draft->luggageKg,
            batteryStartPct: $draft->batteryStartPct,
            fuelStationId: $fuelPrice->stationId,
        ));

        return new TripEstimate($route, $fuelPrice, $energyPrice, $breakdown);
    }

    /**
     * Distancia y desnivel del trayecto. Lo introducido a mano manda siempre:
     * si alguien conoce el dato real, ninguna API debe pisárselo.
     */
    private function resolveRoute(TripDraft $draft): RouteResult
    {
        $route = match (true) {
            $draft->hasManualDistance() => new RouteResult(
                distanceM: $draft->manualDistanceM,
                ascentM: $draft->manualAscentM ?? 0,
                descentM: $draft->manualDescentM ?? 0,
                source: 'manual',
            ),
            $draft->hasCoordinates() => $this->planner->plan(
                $draft->originLat,
                $draft->originLon,
                $draft->destinationLat,
                $draft->destinationLon,
            ),
            default => new RouteResult(0, 0, 0, 'manual', warning: 'Trayecto sin distancia: coste cero.'),
        };

        if (! $draft->roundTrip) {
            return $route;
        }

        // En ida y vuelta lo que se sube a la ida se baja a la vuelta: los
        // acumulados se cruzan, no se duplican.
        return new RouteResult(
            distanceM: $route->distanceM * 2,
            ascentM: $route->ascentM + $route->descentM,
            descentM: $route->descentM + $route->ascentM,
            source: $route->source,
            durationS: $route->durationS === null ? null : $route->durationS * 2,
            geometry: $route->geometry,
            warning: $route->warning,
        );
    }
}
