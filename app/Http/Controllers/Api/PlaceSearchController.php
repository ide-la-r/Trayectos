<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Services\Geocoding\GeocodingClient;
use App\Support\FuelArea;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlaceSearchController extends Controller
{
    public function __invoke(Request $request, GeocodingClient $geocoder): JsonResponse
    {
        $query = (string) $request->query('q', '');
        [$lat, $lon] = $this->whereFrom($request);

        return response()->json([
            'results' => array_map(
                fn ($place) => $place->toArray(),
                $geocoder->search($query, nearLat: $lat, nearLon: $lon),
            ),
        ]);
    }

    /**
     * Desde dónde está buscando esta persona.
     *
     * Sin esto, «Calle Larios» devolvía primero una de Toledo: para el
     * buscador las dos valen lo mismo y no sabe desde dónde se pregunta.
     *
     * Se mira primero la zona elegida en la pantalla de precios, que es donde
     * uno ha dicho explícitamente dónde está. Si no ha elegido ninguna, sirve
     * el origen de su último viaje: quien sale siempre de Málaga va a seguir
     * buscando cosas de por allí.
     *
     * NO se le pide la ubicación al navegador. Un permiso de ubicación para
     * ordenar una lista de resultados no compensa, y quien quiera afinarlo ya
     * tiene la zona de precios.
     *
     * @return array{0: float|null, 1: float|null}
     */
    private function whereFrom(Request $request): array
    {
        $area = FuelArea::fromArray($request->session()->get(FuelArea::SESSION_KEY));

        if ($area) {
            return [$area->lat, $area->lon];
        }

        $groupIds = $request->user()->memberships()->where('active', true)->pluck('group_id');

        $trip = Trip::whereIn('group_id', $groupIds)
            ->whereNotNull('origin_lat')
            ->whereNotNull('origin_lon')
            ->latest('travelled_on')
            ->first(['origin_lat', 'origin_lon']);

        return $trip
            ? [(float) $trip->origin_lat, (float) $trip->origin_lon]
            : [null, null];
    }
}
