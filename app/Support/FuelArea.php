<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\FuelStation;
use Illuminate\Support\Collection;

/**
 * La zona en la que alguien busca precios: un punto y un radio alrededor.
 *
 * La provincia entera no vale como «zona». Málaga va de Ronda a Nerja, así que
 * decirle a alguien que la gasolinera más barata está a noventa kilómetros no
 * le sirve de nada: un precio sólo es útil si está donde esa persona reposta.
 *
 * La zona se guarda en la sesión, no en la base de datos. Con SESSION_DRIVER a
 * base de datos y treinta días de vida se recuerda de sobra entre visitas, y
 * así la ubicación de nadie queda guardada de forma permanente.
 */
final class FuelArea
{
    /** Radios que se ofrecen en pantalla, en kilómetros. */
    public const RADII = [5, 10, 25, 50];

    /** El mismo que usa el estimador de viajes para elegir gasolinera. */
    public const DEFAULT_RADIUS_KM = 25;

    public const SESSION_KEY = 'fuel_area';

    /**
     * En minúscula porque casi siempre va dentro de una frase («a menos de
     * 5 km de tu ubicación»). Donde hace de título se capitaliza allí.
     */
    public const DEFAULT_LABEL = 'tu ubicación';

    public readonly int $radiusKm;

    public readonly string $label;

    /** @var Collection<int, int>|null */
    private ?Collection $stationIds = null;

    public function __construct(
        public readonly float $lat,
        public readonly float $lon,
        ?int $radiusKm = null,
        ?string $label = null,
    ) {
        $this->radiusKm = self::normalizeRadius($radiusKm);
        $this->label = trim((string) $label) ?: self::DEFAULT_LABEL;
    }

    public static function fromArray(mixed $data): ?self
    {
        if (! is_array($data) || ! isset($data['lat'], $data['lon'])) {
            return null;
        }

        return new self(
            lat: (float) $data['lat'],
            lon: (float) $data['lon'],
            radiusKm: isset($data['radius_km']) ? (int) $data['radius_km'] : null,
            label: isset($data['label']) ? (string) $data['label'] : null,
        );
    }

    /** @return array{lat: float, lon: float, radius_km: int, label: string} */
    public function toArray(): array
    {
        return [
            'lat' => $this->lat,
            'lon' => $this->lon,
            'radius_km' => $this->radiusKm,
            'label' => $this->label,
        ];
    }

    public function withRadius(?int $radiusKm): self
    {
        return new self($this->lat, $this->lon, $radiusKm, $this->label);
    }

    /**
     * Un radio que no esté en la lista se ignora: el valor llega de un
     * formulario y nadie tiene que poder pedir «todas las de España» pasando
     * un número enorme por la URL.
     */
    public static function normalizeRadius(mixed $value): int
    {
        return in_array((int) $value, self::RADII, true)
            ? (int) $value
            : self::DEFAULT_RADIUS_KM;
    }

    /**
     * Tres decimales son unos cien metros: para buscar gasolineras sobra, y
     * así no se guarda el portal exacto de nadie.
     */
    public static function roundCoordinate(float $value): float
    {
        return round($value, 3);
    }

    /**
     * Las estaciones que caen dentro de la zona.
     *
     * Dos pasos a propósito: la caja envolvente la filtra SQL —portable, sin
     * trigonometría dentro de la base de datos— y la distancia real la afina
     * PHP, porque las esquinas de la caja quedan hasta un 41 % más lejos que
     * el radio. Sin ese segundo paso el listado enseñaría «32 km» bajo un
     * filtro de 25.
     *
     * @return Collection<int, int>
     */
    public function stationIds(): Collection
    {
        return $this->stationIds ??= FuelStation::near($this->lat, $this->lon, (float) $this->radiusKm)
            ->get(['id', 'lat', 'lon'])
            ->filter(function (FuelStation $station): bool {
                $distance = $this->distanceTo($station->lat, $station->lon);

                return $distance !== null && $distance <= $this->radiusKm;
            })
            ->pluck('id')
            ->values();
    }

    public function distanceTo(int|float|string|null $lat, int|float|string|null $lon): ?float
    {
        if ($lat === null || $lon === null) {
            return null;
        }

        return Geo::haversineKm($this->lat, $this->lon, (float) $lat, (float) $lon);
    }
}
