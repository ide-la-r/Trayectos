<?php

declare(strict_types=1);

namespace App\Services\Routing;

final class RouteResult
{
    /**
     * @param  string  $source  ors | haversine | manual
     * @param  array<int, array{0: float, 1: float, 2?: float}>|null  $geometry
     */
    public function __construct(
        public readonly int $distanceM,
        public readonly int $ascentM,
        public readonly int $descentM,
        public readonly string $source,
        public readonly ?int $durationS = null,
        public readonly ?array $geometry = null,
        public readonly ?string $warning = null,
    ) {}

    public function distanceKm(): float
    {
        return round($this->distanceM / 1000, 1);
    }

    public function isEstimate(): bool
    {
        return $this->source !== 'ors';
    }

    public function toArray(): array
    {
        return [
            'distance_m' => $this->distanceM,
            'ascent_m' => $this->ascentM,
            'descent_m' => $this->descentM,
            'duration_s' => $this->durationS,
            'source' => $this->source,
            'warning' => $this->warning,
        ];
    }
}
