<?php

declare(strict_types=1);

namespace App\Enums;

enum FuelKind: string
{
    case Gasoline95 = 'G95E5';
    case Gasoline98 = 'G98E5';
    case Diesel = 'GOA';
    case Lpg = 'GLP';
    case Cng = 'GNC';
    case None = 'NONE';

    public function label(): string
    {
        return match ($this) {
            self::Gasoline95 => 'Gasolina 95 E5',
            self::Gasoline98 => 'Gasolina 98 E5',
            self::Diesel => 'Gasóleo A',
            self::Lpg => 'GLP (autogás)',
            self::Cng => 'Gas natural comprimido',
            self::None => 'Sin combustible (eléctrico)',
        };
    }

    /** Unidad en la que el Ministerio publica el precio de este producto. */
    public function unit(): string
    {
        return match ($this) {
            self::Lpg, self::Cng => 'kg',
            self::None => 'kWh',
            default => 'L',
        };
    }

    /**
     * Nombre exacto del campo en la respuesta de la API del Ministerio.
     * Ojo: llevan espacios y acentos, y el valor viene como string con coma.
     */
    public function mitecoField(): ?string
    {
        return match ($this) {
            self::Gasoline95 => 'Precio Gasolina 95 E5',
            self::Gasoline98 => 'Precio Gasolina 98 E5',
            self::Diesel => 'Precio Gasoleo A',
            self::Lpg => 'Precio Gases licuados del petróleo',
            self::Cng => 'Precio Gas Natural Comprimido',
            self::None => null,
        };
    }

    public function energyPerUnitJoules(): ?float
    {
        $value = config("trayectos.physics.lhv_joules_per_unit.{$this->value}");

        return $value === null ? null : (float) $value;
    }

    public function fallbackPriceMilli(): int
    {
        return (int) (config("trayectos.fallback_prices.fuel_milli.{$this->value}")
            ?? config('trayectos.fallback_prices.kwh_milli'));
    }

    /** Productos que realmente se sincronizan desde el Ministerio. */
    public static function purchasable(): array
    {
        return array_filter(self::cases(), fn (self $case) => $case !== self::None);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
