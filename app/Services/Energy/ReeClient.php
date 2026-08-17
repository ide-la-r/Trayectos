<?php

declare(strict_types=1);

namespace App\Services\Energy;

use App\Models\EnergyPrice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Precio diario de la electricidad desde apidatos.ree.es (sin clave).
 *
 * Contrato verificado el 2026-08-17: el endpoint sólo acepta time_trunc=hour
 * (con 'day' devuelve 400) y trae dos series — 'PVPC' (id 1001, precio
 * minorista regulado sin IVA) y 'Precio mercado spot' (id 600, mayorista).
 * Se toma la media horaria del PVPC, no el spot: el spot no lo paga nadie.
 *
 * Sigue siendo un respaldo. Quien carga en casa con tarifa fija debería
 * declarar su precio real, que es el que manda en EnergyPriceResolver.
 */
final class ReeClient
{
    public function syncDailyPrice(): ?EnergyPrice
    {
        $day = now()->startOfDay();

        $response = Http::withHeaders(['Accept' => 'application/json'])
            ->timeout((int) config('trayectos.ree.timeout'))
            ->retry(2, 1000, throw: false)
            ->get(rtrim((string) config('trayectos.ree.base_url'), '/').'/es/datos/mercados/precios-mercados-tiempo-real', [
                'start_date' => $day->format('Y-m-d\T00:00'),
                'end_date' => $day->format('Y-m-d\T23:59'),
                'time_trunc' => 'hour',
            ]);

        if ($response->failed()) {
            Log::info('REE no ha respondido', ['status' => $response->status()]);

            return null;
        }

        $values = $this->pvpcValues($response->json('included', []));

        if ($values === []) {
            return null;
        }

        // €/MWh -> €/kWh -> milésimas de euro, con el IVA añadido
        $averagePerMwh = array_sum($values) / count($values);
        $priceMilli = (int) round(
            ($averagePerMwh / 1000) * (float) config('trayectos.ree.retail_multiplier') * 1000
        );

        if ($priceMilli <= 0) {
            return null;
        }

        return EnergyPrice::updateOrCreate(
            ['source' => 'ree', 'observed_at' => $day],
            ['price_milli' => $priceMilli],
        );
    }

    /** @return array<int, float> */
    private function pvpcValues(array $included): array
    {
        $wanted = (string) config('trayectos.ree.series_title');

        $series = collect($included)
            ->first(fn (array $serie) => ($serie['type'] ?? null) === $wanted
                || data_get($serie, 'attributes.title') === $wanted)
            ?? ($included[0] ?? null);

        if (! $series) {
            return [];
        }

        return collect(data_get($series, 'attributes.values', []))
            ->pluck('value')
            ->filter(fn ($value) => is_numeric($value) && $value > 0)
            ->map(fn ($value) => (float) $value)
            ->values()
            ->all();
    }
}
