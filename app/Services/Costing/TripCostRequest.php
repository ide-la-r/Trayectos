<?php

declare(strict_types=1);

namespace App\Services\Costing;

use App\Models\Vehicle;

/** Entrada completa del cálculo de coste de un trayecto. */
final class TripCostRequest
{
    public function __construct(
        public readonly Vehicle $vehicle,
        public readonly int $distanceM,
        public readonly int $ascentM,
        public readonly int $descentM,
        public readonly int $occupants,
        public readonly int $fuelPriceMilli,
        public readonly int $energyPriceMilli,
        public readonly int $luggageKg = 0,
        public readonly int $batteryStartPct = 0,
        public readonly ?int $fuelStationId = null,
    ) {}

    public function distanceKm(): float
    {
        return $this->distanceM / 1000;
    }

    /** Masa real en carretera: tara + ocupantes + equipaje. */
    public function massKg(): float
    {
        $occupantWeight = (float) config('trayectos.physics.occupant_weight_kg');

        return $this->vehicle->kerb_weight_kg
            + ($occupantWeight * max($this->occupants, 1))
            + $this->luggageKg;
    }
}
