<?php

declare(strict_types=1);

namespace App\Services\Trips;

use App\Models\Group;
use App\Models\Trip;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Los viajes que este grupo repite.
 *
 * No hay tabla de plantillas y no hace falta: un viaje que se repite ya está
 * apuntado varias veces, con su coche, su conductor y su gente. Deducirlos del
 * histórico evita que nadie tenga que crear y mantener plantillas a mano, que
 * es trabajo para ahorrar trabajo.
 *
 * Se ofrece siempre el último apuntado de cada ruta: es el que trae el coche y
 * los acompañantes más recientes, que es lo que casi siempre se quiere repetir.
 */
final class FrequentTrips
{
    /** Con una vez no es «el de siempre», es un viaje. */
    private const MIN_TIMES = 2;

    /**
     * @return Collection<int, object{trip: Trip, times: int}>
     */
    public function forGroup(Group $group, int $limit = 3): Collection
    {
        $routes = DB::table('trips')
            ->where('group_id', $group->id)
            // Un viaje anulado suele ser uno que se apuntó mal: no es plantilla
            // de nada. Se mira el asiento porque anular no marca el viaje, crea
            // el asiento contrario.
            ->whereNotExists(fn ($query) => $query
                ->select(DB::raw(1))
                ->from('journal_entries as reversals')
                ->whereColumn('reversals.reverses_id', 'trips.journal_entry_id'))
            ->groupBy('origin_label', 'destination_label', 'round_trip')
            ->havingRaw('count(*) >= ?', [self::MIN_TIMES])
            ->orderByRaw('count(*) desc')
            ->orderByRaw('max(id) desc')
            ->limit($limit)
            ->get([
                DB::raw('count(*) as times'),
                DB::raw('max(id) as last_id'),
            ]);

        if ($routes->isEmpty()) {
            return collect();
        }

        $trips = Trip::with('vehicle')
            ->whereIn('id', $routes->pluck('last_id'))
            ->get()
            ->keyBy('id');

        return $routes
            ->map(fn (object $route) => (object) [
                'trip' => $trips[$route->last_id] ?? null,
                'times' => (int) $route->times,
            ])
            ->filter(fn (object $row) => $row->trip !== null)
            ->values();
    }
}
