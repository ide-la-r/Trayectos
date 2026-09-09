<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreTripRequest;
use App\Models\Group;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Services\Ledger\LedgerService;
use App\Services\Trips\FrequentTrips;
use App\Services\Trips\RouteProfiler;
use App\Services\Trips\TripDraft;
use App\Services\Trips\TripRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class TripController extends Controller
{
    public function create(Request $request, Group $group, FrequentTrips $frequent): View
    {
        return view('trips.create', [
            'group' => $group,
            'member' => $request->attributes->get('group_member'),
            'members' => $group->activeMembers()->with('user')->get(),
            'vehicles' => $this->availableVehicles($group),
            // Los que este grupo repite, para no rellenarlos otra vez a mano
            'frequent' => $frequent->forGroup($group),
        ]);
    }

    /**
     * Volver a apuntar un viaje que ya se hizo.
     *
     * No se crea nada: se rellena el formulario con lo de aquel viaje y se
     * redirige a él, así que sigue habiendo una confirmación de por medio.
     * Apuntar un viaje mueve dinero en el libro, y eso no puede pasar de un
     * clic sin que nadie mire la fecha ni quién iba.
     *
     * El relleno viaja por la bolsa de «entrada anterior», que es la que el
     * formulario ya lee con old(): así no hay dos caminos distintos para
     * rellenar los mismos campos.
     */
    public function repeat(Request $request, Group $group, Trip $trip): RedirectResponse
    {
        abort_unless($trip->group_id === $group->id, 404);

        return redirect()
            ->route('trips.create', $group)
            ->withInput($this->asDraft($group, $trip));
    }

    /**
     * Aquel viaje, convertido en borrador de éste.
     *
     * La fecha NO se copia: se repite el trayecto, no el día. Las notas
     * tampoco, que eran de aquella vez.
     *
     * @return array<string, mixed>
     */
    private function asDraft(Group $group, Trip $trip): array
    {
        $draft = [
            'origin_label' => $trip->origin_label,
            'origin_lat' => $trip->origin_lat,
            'origin_lon' => $trip->origin_lon,
            'destination_label' => $trip->destination_label,
            'destination_lat' => $trip->destination_lat,
            'destination_lon' => $trip->destination_lon,
            'round_trip' => $trip->round_trip ? '1' : null,
            'luggage_kg' => $trip->luggage_kg,
            'battery_start_pct' => $trip->battery_start_pct,
        ];

        // Sólo si siguen estando: sin esto el desplegable se queda con el
        // primero de la lista y se apuntaría el viaje con otro coche.
        if ($this->availableVehicles($group)->contains('id', $trip->vehicle_id)) {
            $draft['vehicle_id'] = $trip->vehicle_id;
        }

        if ($group->activeMembers()->whereKey($trip->driver_member_id)->exists()) {
            $draft['driver_member_id'] = $trip->driver_member_id;
        }

        $active = $group->activeMembers()->pluck('id');

        foreach ($trip->passengers as $passenger) {
            if ($active->doesntContain($passenger->group_member_id)) {
                continue;
            }

            $draft['passengers'][] = $passenger->group_member_id;
            $draft['weights'][$passenger->group_member_id] = rtrim(rtrim((string) $passenger->weight, '0'), '.');
        }

        return $draft + $this->manualRoute($trip);
    }

    /**
     * La distancia corregida a mano, si aquel viaje la llevaba.
     *
     * Lo de la ida y vuelta tiene truco: el formulario pide la ida y el
     * estimador la dobla, así que lo guardado hay que partirlo. El desnivel no
     * se puede deshacer —lo que se sube a la ida se baja a la vuelta, y en la
     * tabla ya vienen sumados— así que en ida y vuelta se deja en blanco antes
     * que inventarse un reparto.
     *
     * @return array<string, mixed>
     */
    private function manualRoute(Trip $trip): array
    {
        if ($trip->route_source !== 'manual' || ! $trip->distance_m) {
            return [];
        }

        $distanceM = $trip->round_trip ? $trip->distance_m / 2 : $trip->distance_m;

        return array_filter([
            'distance_km' => round($distanceM / 1000, 1),
            'ascent_m' => $trip->round_trip ? null : $trip->ascent_m,
            'descent_m' => $trip->round_trip ? null : $trip->descent_m,
        ], fn ($value) => $value !== null);
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

    public function show(Request $request, Group $group, Trip $trip, RouteProfiler $profiler): View
    {
        abort_unless($trip->group_id === $group->id, 404);

        return view('trips.show', [
            'group' => $group,
            'member' => $request->attributes->get('group_member'),
            'trip' => $trip->load(['vehicle', 'driver.user', 'passengers.member.user', 'journalEntry.lines.member.user']),
            // Null cuando la ruta se estimó en línea recta: entonces hay
            // desnivel pero no recorrido que dibujar.
            'profile' => $profiler->profileFor($trip),
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
