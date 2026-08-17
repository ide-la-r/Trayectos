<?php

declare(strict_types=1);

namespace App\Services\Fuel;

use App\Enums\FuelKind;
use App\Models\FuelPrice;
use App\Models\FuelStation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Vuelca el catálogo del Ministerio en la copia local. Idempotente: la clave
 * única (estación, producto, momento de la observación) hace que repetir una
 * sincronización no duplique nada.
 */
final class FuelPriceSynchronizer
{
    public function __construct(private readonly MitecoClient $client) {}

    /** @return array{stations: int, prices: int, observed_at: Carbon} */
    public function sync(?array $provinceIds = null): array
    {
        $provinceIds ??= config('trayectos.miteco.provinces');

        $stationCount = 0;
        $priceCount = 0;
        $observedAt = now();

        $batches = $provinceIds === []
            ? [$this->client->allStations()]
            : array_map(fn (string $id) => $this->client->stationsByProvince($id), $provinceIds);

        foreach ($batches as $batch) {
            $observedAt = $batch['observed_at'];

            foreach (array_chunk($batch['stations'], 200) as $chunk) {
                DB::transaction(function () use ($chunk, $observedAt, &$stationCount, &$priceCount) {
                    foreach ($chunk as $raw) {
                        $station = $this->upsertStation($raw);

                        if (! $station) {
                            continue;
                        }

                        $stationCount++;
                        $priceCount += $this->upsertPrices($station, $raw, $observedAt);
                    }
                });
            }
        }

        Log::info('Precios de carburante sincronizados', [
            'estaciones' => $stationCount,
            'precios' => $priceCount,
        ]);

        return [
            'stations' => $stationCount,
            'prices' => $priceCount,
            'observed_at' => $observedAt,
        ];
    }

    private function upsertStation(array $raw): ?FuelStation
    {
        $ideess = (int) ($raw['IDEESS'] ?? 0);

        if ($ideess === 0) {
            return null;
        }

        return FuelStation::updateOrCreate(
            ['ideess' => $ideess],
            [
                'label' => $this->clean($raw['Rótulo'] ?? null, 120),
                'address' => $this->clean($raw['Dirección'] ?? null, 255),
                'municipality' => $this->clean($raw['Municipio'] ?? $raw['Localidad'] ?? null, 120),
                'province' => $this->clean($raw['Provincia'] ?? null, 120),
                'province_id' => $this->clean($raw['IDProvincia'] ?? null, 4),
                'postal_code' => $this->clean($raw['C.P.'] ?? null, 10),
                'schedule' => $this->clean($raw['Horario'] ?? null, 120),
                'lat' => $this->decimal($raw['Latitud'] ?? null),
                'lon' => $this->decimal($raw['Longitud (WGS84)'] ?? null),
            ]
        );
    }

    private function upsertPrices(FuelStation $station, array $raw, Carbon $observedAt): int
    {
        $rows = [];

        foreach (FuelKind::purchasable() as $kind) {
            $field = $kind->mitecoField();
            $priceMilli = $this->priceMilli($raw[$field] ?? null);

            if ($priceMilli === null) {
                continue;   // la estación no vende ese producto
            }

            $rows[] = [
                'fuel_station_id' => $station->id,
                'fuel_kind' => $kind->value,
                'price_milli' => $priceMilli,
                'observed_at' => $observedAt,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows === []) {
            return 0;
        }

        FuelPrice::upsert($rows, ['fuel_station_id', 'fuel_kind', 'observed_at'], ['price_milli', 'updated_at']);

        return count($rows);
    }

    /** '1,459' => 1459 milésimas. Cadena vacía => null. */
    private function priceMilli(?string $raw): ?int
    {
        if (blank($raw)) {
            return null;
        }

        $value = (float) str_replace(',', '.', trim($raw));

        return $value > 0 ? (int) round($value * 1000) : null;
    }

    private function decimal(?string $raw): ?float
    {
        if (blank($raw)) {
            return null;
        }

        return (float) str_replace(',', '.', trim($raw));
    }

    private function clean(?string $value, int $length): ?string
    {
        if (blank($value)) {
            return null;
        }

        return mb_substr(trim($value), 0, $length);
    }
}
