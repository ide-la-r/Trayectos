<?php

declare(strict_types=1);

namespace App\Services\Trips;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;

/** Datos de un trayecto tal y como llegan del formulario, antes de calcular. */
final class TripDraft
{
    /**
     * @param  array<int, float>  $passengerWeights  [group_member_id => peso]
     */
    public function __construct(
        public readonly Group $group,
        public readonly Vehicle $vehicle,
        public readonly GroupMember $driver,
        public readonly Carbon $travelledOn,
        public readonly string $originLabel,
        public readonly string $destinationLabel,
        public readonly array $passengerWeights,
        public readonly ?float $originLat = null,
        public readonly ?float $originLon = null,
        public readonly ?float $destinationLat = null,
        public readonly ?float $destinationLon = null,
        public readonly bool $roundTrip = false,
        public readonly ?int $manualDistanceM = null,
        public readonly ?int $manualAscentM = null,
        public readonly ?int $manualDescentM = null,
        public readonly int $luggageKg = 0,
        public readonly int $batteryStartPct = 0,
        public readonly ?string $notes = null,
        public readonly ?int $createdBy = null,
    ) {}

    public function hasCoordinates(): bool
    {
        return $this->originLat !== null && $this->originLon !== null
            && $this->destinationLat !== null && $this->destinationLon !== null;
    }

    public function hasManualDistance(): bool
    {
        return $this->manualDistanceM !== null && $this->manualDistanceM > 0;
    }

    /** Ocupantes del coche: el conductor cuenta siempre, aunque no pague parte. */
    public function occupants(): int
    {
        $ids = array_keys($this->passengerWeights);

        if (! in_array($this->driver->id, $ids, true)) {
            $ids[] = $this->driver->id;
        }

        return max(1, count($ids));
    }
}
