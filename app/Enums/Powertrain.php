<?php

declare(strict_types=1);

namespace App\Enums;

enum Powertrain: string
{
    case Combustion = 'ICE';
    case Hybrid = 'HEV';
    case PluginHybrid = 'PHEV';
    case Electric = 'BEV';

    public function label(): string
    {
        return match ($this) {
            self::Combustion => 'Combustión',
            self::Hybrid => 'Híbrido',
            self::PluginHybrid => 'Híbrido enchufable',
            self::Electric => 'Eléctrico',
        };
    }

    /** El tramo térmico existe en todo lo que no sea 100 % eléctrico. */
    public function burnsFuel(): bool
    {
        return $this !== self::Electric;
    }

    /** Sólo estas tecnologías pueden mover el coche con energía de la red. */
    public function drivesOnBattery(): bool
    {
        return in_array($this, [self::PluginHybrid, self::Electric], true);
    }

    /**
     * El híbrido no enchufable tiene batería, pero como amortiguador: toda su
     * energía viene del combustible. Aun así limita cuánto descenso recupera.
     */
    public function hasBattery(): bool
    {
        return $this !== self::Combustion;
    }

    public function defaultRegenFactor(): float
    {
        return (float) config("trayectos.physics.defaults.{$this->value}.regen");
    }

    public function defaultThermalEfficiency(): ?float
    {
        $value = config("trayectos.physics.defaults.{$this->value}.thermal");

        return $value === null ? null : (float) $value;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
