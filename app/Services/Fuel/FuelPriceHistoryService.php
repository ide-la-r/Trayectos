<?php

declare(strict_types=1);

namespace App\Services\Fuel;

use App\Enums\FuelKind;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Serie histórica de precios de carburante.
 *
 * No hace falta recolectar nada nuevo: la tabla fuel_prices tiene una clave
 * única por estación, producto y momento de observación, así que cada
 * sincronización del cron viene acumulando una serie temporal desde el primer
 * despliegue. Lo único que faltaba era mirarla.
 *
 * El ámbito son las estaciones sincronizadas, que por construcción son las de
 * las provincias configuradas en MITECO_PROVINCES: preguntar por «mi zona» no
 * necesita geolocalización porque la copia local ya es la zona.
 */
final class FuelPriceHistoryService
{
    /**
     * Serie diaria del mínimo y la media de la zona.
     *
     * @return Collection<int, object{day: string, min_milli: int, avg_milli: int, stations: int}>
     */
    public function dailySeries(FuelKind $kind, int $days = 30): Collection
    {
        /*
         * Se agrupa por observed_at —una columna, portable— y el paso a días se
         * hace en PHP. La alternativa era una expresión de fecha en SQL, pero
         * eso obliga a ramificar por driver (strftime en SQLite, to_char en
         * PostgreSQL) y la suite corre en SQLite: la rama de producción no la
         * ejercitaría ningún test y sólo podría fallar desplegada.
         *
         * No es caro: el cron sincroniza cuatro veces al día, así que treinta
         * días son unas 120 filas, no las cientos de miles que tiene la tabla.
         */
        $batches = DB::table('fuel_prices')
            ->where('fuel_kind', $kind->value)
            ->where('observed_at', '>=', now()->subDays($days))
            ->groupBy('observed_at')
            ->orderBy('observed_at')
            ->select(
                'observed_at',
                DB::raw('MIN(price_milli) AS min_milli'),
                DB::raw('SUM(price_milli) AS sum_milli'),
                DB::raw('COUNT(*) AS observations'),
                DB::raw('COUNT(DISTINCT fuel_station_id) AS stations'),
            )
            ->get();

        return $batches
            ->groupBy(fn ($row) => Carbon::parse($row->observed_at)->toDateString())
            ->map(fn (Collection $ofDay, string $day) => (object) [
                'day' => $day,
                'min_milli' => (int) $ofDay->min('min_milli'),
                // Media ponderada por número de observaciones: promediar las
                // medias de cada sincronización daría otro número si una trae
                // más estaciones que otra.
                'avg_milli' => (int) round($ofDay->sum('sum_milli') / max(1, $ofDay->sum('observations'))),
                // Cuántas estaciones informaron ese día. Se toma el máximo de
                // las sincronizaciones del día: el conjunto es el mismo en cada
                // pasada, así que sumarlas contaría cada estación varias veces.
                'stations' => (int) $ofDay->max('stations'),
            ])
            ->sortKeys()
            ->values();
    }

    /**
     * Resumen para decidir si conviene llenar hoy o esperar.
     *
     * @return object{
     *     kind: FuelKind,
     *     days: int,
     *     latest: ?object,
     *     min_milli: ?int,
     *     avg_milli: ?int,
     *     change_milli: ?int,
     *     change_pct: ?float,
     *     direction: string,
     *     verdict: string,
     *     series: Collection
     * }
     */
    public function summary(FuelKind $kind, int $days = 30): object
    {
        $series = $this->dailySeries($kind, $days);
        $latest = $series->last();
        $first = $series->first();

        // Con uno o dos días no hay tendencia que enseñar, y fingirla sería
        // peor que no decir nada: el aviso lo dice y ya está.
        $enough = $series->count() >= 3;

        $change = $enough ? $latest->avg_milli - $first->avg_milli : null;
        $pct = $enough && $first->avg_milli > 0
            ? round($change / $first->avg_milli * 100, 1)
            : null;

        // Umbral de medio céntimo por litro: por debajo es ruido de que unas
        // gasolineras informen antes que otras, no un movimiento del mercado.
        $direction = match (true) {
            ! $enough => 'unknown',
            $change > 5 => 'up',
            $change < -5 => 'down',
            default => 'flat',
        };

        return (object) [
            'kind' => $kind,
            'days' => $days,
            'latest' => $latest,
            'min_milli' => $latest?->min_milli,
            'avg_milli' => $latest?->avg_milli,
            'change_milli' => $change,
            'change_pct' => $pct,
            'direction' => $direction,
            'verdict' => $this->verdict($direction, $series->count()),
            'series' => $series,
        ];
    }

    /**
     * Las estaciones más baratas ahora mismo, con su última observación.
     *
     * @return Collection<int, object>
     */
    public function cheapestStations(FuelKind $kind, int $limit = 5): Collection
    {
        // Sólo la última observación de cada estación: sin esto, una gasolinera
        // aparecería tantas veces como veces se haya sincronizado.
        $latest = DB::table('fuel_prices')
            ->where('fuel_kind', $kind->value)
            ->groupBy('fuel_station_id')
            ->select('fuel_station_id', DB::raw('MAX(observed_at) AS observed_at'));

        return DB::table('fuel_prices')
            ->joinSub($latest, 'ultima', function ($join) {
                $join->on('fuel_prices.fuel_station_id', '=', 'ultima.fuel_station_id')
                    ->on('fuel_prices.observed_at', '=', 'ultima.observed_at');
            })
            ->join('fuel_stations', 'fuel_stations.id', '=', 'fuel_prices.fuel_station_id')
            ->where('fuel_prices.fuel_kind', $kind->value)
            ->orderBy('fuel_prices.price_milli')
            ->limit($limit)
            ->select(
                'fuel_stations.id',
                'fuel_stations.label',
                'fuel_stations.municipality',
                'fuel_stations.address',
                'fuel_stations.lat',
                'fuel_stations.lon',
                'fuel_prices.price_milli',
                'fuel_prices.observed_at',
            )
            ->get();
    }

    private function verdict(string $direction, int $points): string
    {
        return match ($direction) {
            'up' => 'Va subiendo: si te hace falta, mejor llenar hoy.',
            'down' => 'Va bajando: si puedes esperar, esperando ganas.',
            'flat' => 'Estable: llena cuando te venga bien.',
            default => $points === 0
                ? 'Todavía no hay precios sincronizados de este carburante.'
                : 'Aún no hay días suficientes para hablar de tendencia.',
        };
    }
}
