<?php

declare(strict_types=1);

namespace App\Services\Geocoding;

final class PlaceResult
{
    public function __construct(
        public readonly string $label,
        public readonly float $lat,
        public readonly float $lon,
        public readonly ?string $context = null,
    ) {}

    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'lat' => $this->lat,
            'lon' => $this->lon,
            'context' => $this->context,
        ];
    }
}
