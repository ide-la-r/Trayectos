<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\FuelKind;
use App\Services\Fuel\FuelPriceHistoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Precios de carburante de la zona sincronizada: cuánto está hoy, cómo viene
 * moviéndose y dónde está más barato.
 *
 * Los datos ya estaban: el cron los sincroniza cuatro veces al día desde el
 * primer despliegue y fuel_prices guarda cada observación. Esta pantalla es
 * sólo mirarlos.
 */
class FuelPriceController extends Controller
{
    public function __invoke(Request $request, FuelPriceHistoryService $history): View
    {
        // Sólo los carburantes de los que hay precios de verdad: ofrecer una
        // pestaña vacía es prometer un dato que no existe.
        $available = DB::table('fuel_prices')
            ->distinct()
            ->pluck('fuel_kind')
            ->map(fn (string $value) => FuelKind::tryFrom($value))
            ->filter()
            ->values();

        $kind = $this->selectedKind($request, $available);

        return view('fuel.prices', [
            'available' => $available,
            'kind' => $kind,
            'summary' => $kind ? $history->summary($kind) : null,
            'cheapest' => $kind ? $history->cheapestStations($kind) : collect(),
            'provinces' => (array) config('trayectos.miteco.provinces'),
        ]);
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
