<?php

declare(strict_types=1);

namespace App\Services\Fuel;

use App\Enums\FuelKind;
use App\Models\FuelStation;
use App\Support\FuelArea;
use App\Support\FuelBrand;
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
 * Todo acepta una zona opcional. Sin ella el ámbito son las provincias
 * configuradas en MITECO_PROVINCES, que puede ser media comunidad; con ella,
 * las gasolineras que de verdad le pillan de camino a quien pregunta.
 */
final class FuelPriceHistoryService
{
    /**
     * Serie diaria del mínimo y la media de la zona.
     *
     * @return Collection<int, object{day: string, min_milli: int, avg_milli: int, stations: int}>
     */
    public function dailySeries(FuelKind $kind, int $days = 30, ?FuelArea $area = null): Collection
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
            ->when($area, fn ($query) => $query->whereIn('fuel_station_id', $area->stationIds()))
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
     *     area: ?FuelArea,
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
    public function summary(FuelKind $kind, int $days = 30, ?FuelArea $area = null): object
    {
        $series = $this->dailySeries($kind, $days, $area);
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
            'area' => $area,
            'latest' => $latest,
            'min_milli' => $latest?->min_milli,
            'avg_milli' => $latest?->avg_milli,
            'change_milli' => $change,
            'change_pct' => $pct,
            'direction' => $direction,
            'verdict' => $this->verdict($direction, $series->count(), $area),
            'series' => $series,
        ];
    }

    /**
     * Las estaciones más baratas ahora mismo, con su última observación.
     *
     * Cuando hay zona, cada fila lleva además la distancia real hasta el punto
     * elegido: es lo que convierte un ranking de precios en algo accionable.
     *
     * @return Collection<int, object>
     */
    public function cheapestStations(FuelKind $kind, int $limit = 5, ?FuelArea $area = null): Collection
    {
        // Sólo la última observación de cada estación: sin esto, una gasolinera
        // aparecería tantas veces como veces se haya sincronizado.
        $latest = DB::table('fuel_prices')
            ->where('fuel_kind', $kind->value)
            ->when($area, fn ($query) => $query->whereIn('fuel_station_id', $area->stationIds()))
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
            ->get()
            ->map(function (object $row) use ($area): object {
                $distance = $area?->distanceTo($row->lat, $row->lon);
                $row->distance_km = $distance === null ? null : round($distance, 1);

                return $row;
            });
    }

    /**
     * Las gasolineras de la zona, listas para pintarlas en el mapa.
     *
     * Sólo con zona: sin ella el ámbito son las provincias sincronizadas —tres
     * mil estaciones— y eso no es un mapa, es una mancha; además serían
     * cientos de kilobytes viajando con la página para nada.
     *
     * @return Collection<int, object>
     */
    public function stationsForMap(FuelKind $kind, FuelArea $area, int $limit = 400): Collection
    {
        $stations = $this->cheapestStations($kind, $limit, $area)
            ->filter(fn (object $row) => $row->lat !== null && $row->lon !== null)
            ->values();

        // Vienen ordenadas de más barata a más cara, así que el nivel sale de
        // la posición y no del precio: una gasolinera con un precio disparatado
        // arrastraría a todas las demás al nivel «barata» si se midiera por
        // rango.
        $total = $stations->count();
        $cheapestThird = (int) ceil($total / 3);
        $dearestThird = $total - $cheapestThird;

        return $stations->map(fn (object $row, int $position) => (object) [
            'lat' => (float) $row->lat,
            'lon' => (float) $row->lon,
            'label' => $row->label ?: 'Estación sin rótulo',
            // La marca sale del rótulo: el Ministerio no publica ese campo
            'brand' => FuelBrand::for($row->label),
            'municipality' => $row->municipality,
            'address' => $row->address,
            'price' => number_format($row->price_milli / 1000, 3, ',', '.'),
            'unit' => $kind->unit(),
            'distance' => $row->distance_km === null
                ? null
                : number_format($row->distance_km, 1, ',', '.'),
            'tier' => match (true) {
                // Con dos o menos no hay ranking que enseñar
                $total < 3 => 1,
                $position < $cheapestThird => 0,
                $position >= $dearestThird => 2,
                default => 1,
            },
        ]);
    }

    /**
     * Qué provincias hay de verdad en la copia local, por su nombre.
     *
     * Se leen de los datos y no de la configuración a propósito: «29» no le
     * dice nada a nadie, y si la configuración ha cambiado pero la
     * sincronización todavía no ha corrido, lo que importa es lo que hay.
     *
     * @return Collection<int, string>
     */
    public function syncedProvinces(): Collection
    {
        return DB::table('fuel_stations')
            ->whereNotNull('province')
            ->distinct()
            ->orderBy('province')
            ->pluck('province');
    }

    /**
     * La estación sincronizada más cercana al punto, esté donde esté.
     *
     * Es para cuando la zona se queda vacía: decir «prueba a ampliar el radio»
     * cuando la más cercana está a cuatrocientos kilómetros es un consejo que
     * no puede funcionar. Con esto se puede decir lo que pasa de verdad.
     *
     * Carga todas las estaciones en memoria, así que sólo se llama en ese
     * caso; son unos miles de filas de cuatro columnas.
     */
    public function nearestStation(FuelArea $area): ?object
    {
        return FuelStation::query()
            ->whereNotNull('lat')
            ->whereNotNull('lon')
            ->get(['id', 'label', 'municipality', 'province', 'lat', 'lon'])
            ->map(fn (FuelStation $station) => (object) [
                'station' => $station,
                'km' => $area->distanceTo($station->lat, $station->lon),
            ])
            ->sortBy('km')
            ->first();
    }

    private function verdict(string $direction, int $points, ?FuelArea $area): string
    {
        if ($direction === 'up') {
            return 'Va subiendo: si te hace falta, mejor llenar hoy.';
        }

        if ($direction === 'down') {
            return 'Va bajando: si puedes esperar, esperando ganas.';
        }

        if ($direction === 'flat') {
            return 'Estable: llena cuando te venga bien.';
        }

        if ($points > 0) {
            return 'Aún no hay días suficientes para hablar de tendencia.';
        }

        /*
         * Con zona no se aconseja nada aquí. Antes decía «prueba a ampliar el
         * radio», que es un consejo imposible de seguir cuando la gasolinera
         * sincronizada más cercana está a cuatrocientos kilómetros. El
         * diagnóstico lo da la pantalla, que sí sabe a qué distancia está.
         */
        return $area
            ? 'No hay precios de este carburante en la zona elegida.'
            : 'Todavía no hay precios sincronizados de este carburante.';
    }
}
