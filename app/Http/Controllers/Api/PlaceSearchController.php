<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Geocoding\GeocodingClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlaceSearchController extends Controller
{
    public function __invoke(Request $request, GeocodingClient $geocoder): JsonResponse
    {
        $query = (string) $request->query('q', '');

        return response()->json([
            'results' => array_map(
                fn ($place) => $place->toArray(),
                $geocoder->search($query),
            ),
        ]);
    }
}
