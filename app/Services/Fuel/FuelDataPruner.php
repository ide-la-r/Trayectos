<?php

declare(strict_types=1);

namespace App\Services\Fuel;

use App\Models\FuelPrice;
use App\Models\FuelStation;
use Illuminate\Support\Collection;

/**
 * Mantiene la copia local acotada: sólo las provincias configuradas y sólo el
 * histórico que la aplicación llega a enseñar.
 *
 * Hasta ahora no se borraba nunca nada. Con cuatro sincronizaciones al día y
 * unos miles de estaciones, fuel_prices crece del orden de cien megas al mes;
 * el plan gratuito de Neon son 500 MB en total, así que sin esto la base de
 * datos se llena sola en unos meses y deja de admitir escrituras.
 */
final class FuelDataPruner
{
    /**
     * La pantalla enseña 30 días; se guardan 45 para tener margen.
     *
     * Con las cuatro provincias que se sincronizan son unos 3,8 MB al día, así
     * que 45 días son 172 MB de los 500 del plan gratuito. Con 90 días serían
     * 344 MB: dos tercios de la base de datos para un histórico que nadie mira.
     */
    public const KEEP_DAYS = 45;

    /** @return array{stations: int, prices: int} */
    public function prune(?array $provinceIds = null, int $keepDays = self::KEEP_DAYS): array
    {
        $provinceIds ??= (array) config('trayectos.miteco.provinces');

        return [
            'stations' => $this->dropForeignProvinces($provinceIds),
            'prices' => $this->dropOldObservations($keepDays),
        ];
    }

    /**
     * Estaciones que ya no se sincronizan porque su provincia salió de la
     * configuración.
     *
     * Sin esto se quedarían en la copia local para siempre y seguirían
     * saliendo en pantalla: es lo que hacía que a alguien de Málaga le
     * apareciera Móstoles entre las más baratas «de su zona».
     */
    private function dropForeignProvinces(array $provinceIds): int
    {
        // Lista vacía significa descarga nacional: entonces no sobra ninguna.
        if ($provinceIds === []) {
            return 0;
        }

        $deleted = 0;

        FuelStation::whereNotNull('province_id')
            ->whereNotIn('province_id', $provinceIds)
            ->select('id')
            ->chunkById(500, function (Collection $stations) use (&$deleted) {
                $ids = $stations->pluck('id');

                // Los precios se borran a mano y no por la cascada de la clave
                // foránea: así no depende de que el driver la esté aplicando.
                FuelPrice::whereIn('fuel_station_id', $ids)->delete();
                $deleted += FuelStation::whereIn('id', $ids)->delete();
            });

        return $deleted;
    }

    private function dropOldObservations(int $keepDays): int
    {
        return FuelPrice::where('observed_at', '<', now()->subDays($keepDays))->delete();
    }
}
