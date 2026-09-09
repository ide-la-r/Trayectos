<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\FuelKind;
use App\Services\Fuel\FuelPriceHistoryService;
use App\Support\FuelArea;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Precios de carburante de la zona: cuánto está hoy, cómo viene moviéndose y
 * dónde está más barato.
 *
 * Los datos ya estaban: el cron los sincroniza cuatro veces al día desde el
 * primer despliegue y fuel_prices guarda cada observación. Esta pantalla es
 * sólo mirarlos, acotados al sitio donde quien pregunta va a repostar.
 */
class FuelPriceController extends Controller
{
    public function index(Request $request, FuelPriceHistoryService $history): View
    {
        /*
         * Los carburantes disponibles se calculan sin la zona a propósito: si
         * dependieran de ella, las pestañas aparecerían y desaparecerían al
         * cambiar el radio. Es más claro dejarlas fijas y explicar después que
         * de ése no hay nada cerca.
         */
        $available = DB::table('fuel_prices')
            ->distinct()
            ->pluck('fuel_kind')
            ->map(fn (string $value) => FuelKind::tryFrom($value))
            ->filter()
            ->values();

        $kind = $this->selectedKind($request, $available);
        $area = FuelArea::fromArray($request->session()->get(FuelArea::SESSION_KEY));

        $summary = $kind ? $history->summary($kind, area: $area) : null;

        return view('fuel.prices', [
            'available' => $available,
            'kind' => $kind,
            'area' => $area,
            'radii' => FuelArea::RADII,
            'summary' => $summary,
            'cheapest' => $kind ? $history->cheapestStations($kind, area: $area) : collect(),
            // Las provincias que hay de verdad, por su nombre: «29» no le dice
            // nada a nadie y la configuración puede ir por delante del dato.
            'provinces' => $history->syncedProvinces(),
            // Sólo cuando la zona se ha quedado vacía: buscar la más cercana
            // carga todas las estaciones y no hace falta si ya hay resultados.
            'nearest' => $area && $summary?->latest === null
                ? $history->nearestStation($area)
                : null,
            // El mapa necesita un dónde. Sin zona no se manda nada: serían las
            // 3.016 estaciones sincronizadas viajando con la página.
            'mapStations' => $kind && $area
                ? $history->stationsForMap($kind, $area)
                : collect(),
        ]);
    }

    /**
     * Elegir la zona, cambiarle el radio o quitarla.
     *
     * Las coordenadas viajan en el cuerpo de un POST y no en la URL: la
     * ubicación de una persona no tiene por qué acabar en el historial del
     * navegador ni en los registros del servidor.
     */
    public function updateArea(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lon' => ['nullable', 'numeric', 'between:-180,180'],
            'label' => ['nullable', 'string', 'max:120'],
            'radius_km' => ['nullable', 'integer'],
            'carburante' => ['nullable', 'string', 'max:16'],
        ]);

        $session = $request->session();
        $current = FuelArea::fromArray($session->get(FuelArea::SESSION_KEY));

        if ($request->boolean('clear')) {
            $session->forget(FuelArea::SESSION_KEY);
        } elseif (isset($data['lat'], $data['lon'])) {
            $session->put(FuelArea::SESSION_KEY, (new FuelArea(
                lat: FuelArea::roundCoordinate((float) $data['lat']),
                lon: FuelArea::roundCoordinate((float) $data['lon']),
                radiusKm: isset($data['radius_km']) ? (int) $data['radius_km'] : $current?->radiusKm,
                label: $data['label'] ?? null,
            ))->toArray());
        } elseif ($current && isset($data['radius_km'])) {
            // Cambiar sólo el radio conserva el punto: es el caso de pulsar
            // «50 km» cuando en 10 no salía nada.
            $session->put(FuelArea::SESSION_KEY, $current->withRadius((int) $data['radius_km'])->toArray());
        }

        return redirect()->route('prices', array_filter([
            'carburante' => $data['carburante'] ?? null,
        ]));
    }

    /**
     * El carburante elegido: el de la URL si vale, si no el del primer coche de
     * la persona —que es el que le interesa— y en último caso el primero que
     * haya sincronizado.
     */
    private function selectedKind(Request $request, $available): ?FuelKind
    {
        if ($available->isEmpty()) {
            return null;
        }

        $asked = FuelKind::tryFrom((string) $request->query('carburante'));

        if ($asked && $available->contains($asked)) {
            return $asked;
        }

        $mine = $request->user()->vehicles()
            ->where('active', true)
            ->pluck('fuel_kind')
            ->map(fn ($value) => $value instanceof FuelKind ? $value : FuelKind::tryFrom((string) $value))
            ->filter(fn (?FuelKind $k) => $k && $available->contains($k))
            ->first();

        return $mine ?? $available->first();
    }
}
