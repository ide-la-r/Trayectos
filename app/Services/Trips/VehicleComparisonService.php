<?php

declare(strict_types=1);

namespace App\Services\Trips;

use App\Models\Vehicle;
use Illuminate\Support\Collection;

/**
 * Cuánto costaría este mismo trayecto con cada coche del grupo.
 *
 * El calculador siempre supo costear cualquier vehículo, pero solo se usaba con
 * el que iba. Corriéndolo sobre todos los coches disponibles, la pregunta «a
 * quién le toca conducir» deja de tener una sola respuesta contable —quien más
 * debe— y pasa a tener también una económica: para un puerto de montaña el
 * híbrido puede salir dos euros más barato que el diésel, y eso lo paga el
 * grupo entero.
 *
 * No es gratis pero casi: la ruta se cachea por coordenadas una semana y la
 * caché se consulta antes de gastar cuota de OpenRouteService, así que comparar
 * cinco coches cuesta una única petición. Los precios sí se resuelven por
 * coche, y tiene que ser así: un diésel y un gasolina no valen lo mismo.
 */
final class VehicleComparisonService
{
    public function __construct(private readonly TripEstimator $estimator) {}

    /**
     * @return Collection<int, object{
     *     vehicle: Vehicle,
     *     cost_cents: int,
     *     per_payer_cents: int,
     *     cheapest: bool,
     *     extra_cents: int,
     *     is_selected: bool
     * }>
     */
    public function compare(TripDraft $draft, int $payerCount = 1): Collection
    {
        $candidates = $this->candidates($draft);

        if ($candidates->count() < 2) {
            // Con un solo coche no hay nada que comparar, y una tabla de una
            // fila es ruido en un formulario que ya es largo.
            return collect();
        }

        $payers = max(1, $payerCount);

        $rows = $candidates->map(function (Vehicle $vehicle) use ($draft, $payers) {
            $estimate = $this->estimator->estimate($draft->withVehicle($vehicle));
            $cost = $estimate->breakdown->totalCents;

            return (object) [
                'vehicle' => $vehicle,
                'cost_cents' => $cost,
                'per_payer_cents' => (int) round($cost / $payers),
                'cheapest' => false,
                'extra_cents' => 0,
                'is_selected' => $vehicle->is($draft->vehicle),
            ];
        })->sortBy('cost_cents')->values();

        $cheapest = (int) $rows->first()->cost_cents;

        return $rows->each(function (object $row) use ($cheapest) {
            $row->cheapest = $row->cost_cents === $cheapest;
            $row->extra_cents = $row->cost_cents - $cheapest;
        });
    }

    /**
     * Coches candidatos: los activos de los miembros activos del grupo con
     * plazas para todos los que van. Sugerir un coche donde no cabe el grupo no
     * sirve de nada, que es el mismo filtro que aplica DriverSuggestionService.
     *
     * @return Collection<int, Vehicle>
     */
    private function candidates(TripDraft $draft): Collection
    {
        return Vehicle::query()
            ->with('owner')
            ->where('active', true)
            ->where('seats', '>=', $draft->occupants())
            ->whereIn('owner_id', $draft->group->activeMembers()->pluck('user_id'))
            ->orderBy('id')
            ->get();
    }
}
