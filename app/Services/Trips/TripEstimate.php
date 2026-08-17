<?php

declare(strict_types=1);

namespace App\Services\Trips;

use App\Services\Costing\TripCostBreakdown;
use App\Services\Fuel\ResolvedPrice;
use App\Services\Routing\RouteResult;

/** Cálculo completo de un trayecto, aún sin guardar. */
final class TripEstimate
{
    public function __construct(
        public readonly RouteResult $route,
        public readonly ResolvedPrice $fuelPrice,
        public readonly ResolvedPrice $energyPrice,
        public readonly TripCostBreakdown $breakdown,
    ) {}

    /** Snapshot que se congela con el viaje al asentarlo. */
    public function snapshot(): array
    {
        return array_merge($this->breakdown->toArray(), [
            'route' => $this->route->toArray(),
            'fuel_price' => $this->fuelPrice->toArray(),
            'energy_price' => $this->energyPrice->toArray(),
        ]);
    }

    /** Versión legible para la previsualización de la interfaz. */
    public function toPreview(int $payerCount): array
    {
        $perPerson = $payerCount > 0
            ? (int) round($this->breakdown->totalCents / $payerCount)
            : $this->breakdown->totalCents;

        return [
            'distance_km' => $this->route->distanceKm(),
            'ascent_m' => $this->route->ascentM,
            'descent_m' => $this->route->descentM,
            'route_source' => $this->route->source,
            'route_warning' => $this->route->warning,
            'total_cents' => $this->breakdown->totalCents,
            'flat_cents' => $this->breakdown->flatCostCents,
            'hill_percent' => $this->breakdown->hillSurchargePercent(),
            'per_person_cents' => $perPerson,
            'litres' => round($this->breakdown->litres, 2),
            'kwh' => round($this->breakdown->kwh, 2),
            'mass_kg' => round($this->breakdown->massKg),
            'fuel_price' => [
                'euros' => round($this->fuelPrice->euros(), 3),
                'source' => $this->fuelPrice->source,
                'description' => $this->fuelPrice->describe(),
            ],
            'energy_price' => [
                'euros' => round($this->energyPrice->euros(), 3),
                'source' => $this->energyPrice->source,
                'description' => $this->energyPrice->describe(),
            ],
        ];
    }
}
