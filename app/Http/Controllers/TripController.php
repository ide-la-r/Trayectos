<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreTripRequest;
use App\Models\Group;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Services\Ledger\LedgerService;
use App\Services\Trips\TripDraft;
use App\Services\Trips\TripRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class TripController extends Controller
{
    public function create(Request $request, Group $group): View
    {
        return view('trips.create', [
            'group' => $group,
            'member' => $request->attributes->get('group_member'),
            'members' => $group->activeMembers()->with('user')->get(),
            'vehicles' => $this->availableVehicles($group),
        ]);
    }

    public function store(StoreTripRequest $request, Group $group, TripRecorder $recorder): RedirectResponse
    {
        $data = $request->validated();

        $vehicle = $this->availableVehicles($group)->firstWhere('id', (int) $data['vehicle_id']);

        if (! $vehicle) {
            return back()->withInput()->withErrors([
                'vehicle_id' => 'Ese coche no pertenece a ningún miembro del grupo.',
            ]);
        }

        $driver = $group->members()->findOrFail($data['driver_member_id']);

        $weights = [];

        foreach ($data['passengers'] as $memberId) {
            $weights[(int) $memberId] = (float) ($data['weights'][$memberId] ?? 1.0);
        }

        $trip = $recorder->record(new TripDraft(
            group: $group,
            vehicle: $vehicle,
            driver: $driver,
            travelledOn: Carbon::parse($data['travelled_on']),
            originLabel: $data['origin_label'],
            destinationLabel: $data['destination_label'],
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
            notes: $data['notes'] ?? null,
            createdBy: $request->user()->id,
        ));

        return redirect()->route('trips.show', [$group, $trip])
            ->with('status', 'Viaje apuntado y repartido.');
    }

    public function show(Request $request, Group $group, Trip $trip): View
    {
        abort_unless($trip->group_id === $group->id, 404);

        return view('trips.show', [
            'group' => $group,
            'member' => $request->attributes->get('group_member'),
            'trip' => $trip->load(['vehicle', 'driver.user', 'passengers.member.user', 'journalEntry.lines.member.user']),
        ]);
    }

    /** Anular no borra: contraasienta y deja el rastro en el libro. */
    public function cancel(Request $request, Group $group, Trip $trip, LedgerService $ledger): RedirectResponse
    {
        abort_unless($trip->group_id === $group->id, 404);

        $member = $request->attributes->get('group_member');
        $isOwnTrip = (int) $trip->created_by === (int) $request->user()->id
            || (int) $trip->driver_member_id === (int) $member->id;

        if (! $isOwnTrip && ! $member->isAdmin()) {
            abort(403, 'Sólo quien apuntó el viaje, quien conducía o un administrador pueden anularlo.');
        }

        if (! $trip->journalEntry) {
            return back()->withErrors(['trip' => 'Este viaje no tiene ningún asiento que anular.']);
        }

        if ($trip->journalEntry->isReversed()) {
            return back()->with('status', 'Este viaje ya estaba anulado.');
        }

        $ledger->reverse(
            $trip->journalEntry,
            "viaje {$trip->origin_label} → {$trip->destination_label} del ".$trip->travelled_on->format('d/m/Y'),
            $request->user()->id,
        );

        return back()->with('status', 'Viaje anulado. El libro conserva el asiento original y su contrario.');
    }

    /** Coches de cualquier miembro del grupo: se conduce el coche de quien toque. */
    private function availableVehicles(Group $group)
    {
        return Vehicle::query()
            ->where('active', true)
            ->whereIn('owner_id', $group->activeMembers()->pluck('user_id'))
            ->with('owner')
            ->orderBy('label')
            ->get();
    }
}
