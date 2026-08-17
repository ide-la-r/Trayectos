<?php

declare(strict_types=1);

namespace App\Services\Trips;

use App\Models\Trip;
use App\Services\Ledger\LedgerService;
use Illuminate\Support\Facades\DB;

/**
 * Persiste un trayecto ya calculado y lo asienta en el libro. Es el único
 * camino por el que se crea un viaje, para que ninguno acabe en la base de
 * datos sin snapshot de cálculo o sin cuadrar en el libro.
 */
final class TripRecorder
{
    public function __construct(
        private readonly TripEstimator $estimator,
        private readonly LedgerService $ledger,
    ) {}

    public function record(TripDraft $draft): Trip
    {
        $estimate = $this->estimator->estimate($draft);
        $route = $estimate->route;

        return DB::transaction(function () use ($draft, $route, $estimate) {
            $trip = Trip::create([
                'group_id' => $draft->group->id,
                'vehicle_id' => $draft->vehicle->id,
                'driver_member_id' => $draft->driver->id,
                'travelled_on' => $draft->travelledOn->toDateString(),
                'origin_label' => $draft->originLabel,
                'destination_label' => $draft->destinationLabel,
                'origin_lat' => $draft->originLat,
                'origin_lon' => $draft->originLon,
                'destination_lat' => $draft->destinationLat,
                'destination_lon' => $draft->destinationLon,
                'round_trip' => $draft->roundTrip,
                'distance_m' => $route->distanceM,
                'ascent_m' => $route->ascentM,
                'descent_m' => $route->descentM,
                'luggage_kg' => $draft->luggageKg,
                'battery_start_pct' => $draft->batteryStartPct,
                'route_source' => $route->source,
                'route_geometry' => $route->geometry,
                'total_cost_cents' => $estimate->breakdown->totalCents,
                'cost_inputs' => $estimate->snapshot(),
                'formula_version' => (int) config('trayectos.physics.formula_version'),
                'notes' => $draft->notes,
                'created_by' => $draft->createdBy,
            ]);

            foreach ($draft->passengerWeights as $memberId => $weight) {
                $trip->passengers()->create([
                    'group_member_id' => (int) $memberId,
                    'weight' => (float) $weight,
                ]);
            }

            $this->ledger->postTrip($trip, $draft->createdBy);

            return $trip->fresh(['passengers', 'journalEntry']);
        });
    }
}
