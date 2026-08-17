<?php

declare(strict_types=1);

namespace App\Services\Fuel;

use Illuminate\Support\Carbon;

final class ResolvedPrice
{
    /**
     * @param  string  $source  station | national_average | manual | ree | fallback
     */
    public function __construct(
        public readonly int $priceMilli,
        public readonly string $source,
        public readonly ?int $stationId = null,
        public readonly ?string $stationLabel = null,
        public readonly ?Carbon $observedAt = null,
        public readonly ?float $distanceKm = null,
    ) {}

    public function isReal(): bool
    {
        return ! in_array($this->source, ['fallback'], true);
    }

    public function euros(): float
    {
        return $this->priceMilli / 1000;
    }

    public function describe(): string
    {
        return match ($this->source) {
            'station' => $this->stationLabel
                ? "{$this->stationLabel} (a {$this->formattedDistance()})"
                : 'Gasolinera cercana',
            'national_average' => 'Media nacional del último dato oficial',
            'manual' => 'Precio introducido a mano',
            'ree' => 'Estimación a partir del mercado eléctrico (REE)',
            default => 'Precio de respaldo: sin dato oficial reciente',
        };
    }

    private function formattedDistance(): string
    {
        return $this->distanceKm === null
            ? 'distancia desconocida'
            : number_format($this->distanceKm, 1, ',', '.').' km';
    }

    public function toArray(): array
    {
        return [
            'price_milli' => $this->priceMilli,
            'source' => $this->source,
            'station_id' => $this->stationId,
            'station_label' => $this->stationLabel,
            'observed_at' => $this->observedAt?->toIso8601String(),
            'distance_km' => $this->distanceKm,
        ];
    }
}
