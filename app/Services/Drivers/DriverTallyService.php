<?php

declare(strict_types=1);

namespace App\Services\Drivers;

use App\Models\Group;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cuántas veces ha puesto cada uno el coche, y cuántos kilómetros.
 *
 * Es un número informativo, no entra en ningún cálculo: el reparto lo decide el
 * libro y el turno lo sugiere DriverSuggestionService. Está porque «quién echa
 * coche más veces» es la pregunta que se hace un grupo, y los kilómetros solos
 * no la contestan: veinte viajes cortos al pueblo pesan en quien conduce mucho
 * más que un viaje largo, y en kilómetros parecen menos.
 *
 * Los viajes anulados no cuentan. Anular no borra la fila —contraasienta en el
 * libro—, así que hay que excluir explícitamente los que tienen un asiento
 * contrario, o el contador subiría con viajes que ya no valen.
 */
final class DriverTallyService
{
    /**
     * @return Collection<int, object{trips: int, meters: int}> indexada por group_member_id
     */
    public function forGroup(Group $group): Collection
    {
        return DB::table('trips')
            ->where('trips.group_id', $group->id)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('journal_entries AS reversals')
                    ->whereColumn('reversals.reverses_id', 'trips.journal_entry_id');
            })
            ->groupBy('trips.driver_member_id')
            ->select(
                'trips.driver_member_id',
                DB::raw('COUNT(*) AS trips'),
                DB::raw('COALESCE(SUM(trips.distance_m), 0) AS meters'),
            )
            ->get()
            ->mapWithKeys(fn ($row) => [
                (int) $row->driver_member_id => (object) [
                    'trips' => (int) $row->trips,
                    'meters' => (int) $row->meters,
                ],
            ]);
    }
}
