<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Vehicle;
use App\Services\Trips\TripDraft;
use App\Services\Trips\TripEstimator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Previsualización del coste mientras se rellena el formulario. Usa el mismo
 * estimador que el guardado definitivo: lo que se ve es lo que se apunta.
 */
class TripEstimateController extends Controller
{
    public function __invoke(Request $request, TripEstimator $estimator): JsonResponse
    {
        $data = $request->validate([
            'group_id' => ['required', 'exists:groups,id'],
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'origin_lat' => ['nullable', 'numeric'],
            'origin_lon' => ['nullable', 'numeric'],
            'destination_lat' => ['nullable', 'numeric'],
            'destination_lon' => ['nullable', 'numeric'],
            'distance_km' => ['nullable', 'numeric', 'min:0', 'max:5000'],
            'ascent_m' => ['nullable', 'integer', 'min:0', 'max:20000'],
            'descent_m' => ['nullable', 'integer', 'min:0', 'max:20000'],
            'round_trip' => ['nullable', 'boolean'],
            'occupants' => ['required', 'integer', 'min:1', 'max:9'],
            'payers' => ['nullable', 'integer', 'min:1', 'max:9'],
            'luggage_kg' => ['nullable', 'integer', 'min:0', 'max:500'],
            'battery_start_pct' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $group = Group::findOrFail($data['group_id']);

        if (! $request->user()->memberIn($group)) {
            abort(403);
        }

        $vehicle = Vehicle::findOrFail($data['vehicle_id']);
        $member = $request->user()->memberIn($group);

        $occupants = (int) $data['occupants'];

        // Sólo interesa el coste, no el reparto: basta con que el número de
        // ocupantes cuadre para que la masa del vehículo sea la correcta. El
        // conductor cuenta como uno de ellos, el resto son plazas anónimas.
        $weights = [$member->id => 1.0];

        for ($seat = 1; $seat < $occupants; $seat++) {
            $weights[-$seat] = 1.0;
        }

        $draft = new TripDraft(
            group: $group,
            vehicle: $vehicle,
            driver: $member,
            travelledOn: now(),
            originLabel: 'previsualización',
            destinationLabel: 'previsualización',
            passengerWeights: $weights,
            originLat: isset($data['origin_lat']) ? (float) $data['origin_lat'] : null,
            originLon: isset($data['origin_lon']) ? (float) $data['origin_lon'] : null,
            destinationLat: isset($data['destination_lat']) ? (float) $data['destination_lat'] : null,
            destinationLon: isset($data['destination_lon']) ? (float) $data['destination_lon'] : null,
            roundTrip: (bool) ($data['round_trip'] ?? false),
            manualDistanceM: isset($data['distance_km']) ? (int) round(((float) $data['distance_km']) * 1000) : null,
            manualAscentM: isset($data['ascent_m']) ? (int) $data['ascent_m'] : null,
            manualDescentM: isset($data['descent_m']) ? (int) $data['descent_m'] : null,
            luggageKg: (int) ($data['luggage_kg'] ?? 0),
            batteryStartPct: (int) ($data['battery_start_pct'] ?? 0),
        );

        $estimate = $estimator->estimate($draft);

        return response()->json($estimate->toPreview(
            payerCount: (int) ($data['payers'] ?? $occupants),
        ));
    }
}
