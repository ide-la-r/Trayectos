<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FuelKind;
use App\Enums\Powertrain;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'owner_id', 'label', 'plate', 'powertrain', 'fuel_kind', 'seats', 'kerb_weight_kg',
    'consumption_l_100', 'consumption_kwh_100', 'battery_kwh_usable', 'ev_range_km',
    'regen_factor', 'thermal_efficiency', 'calibration_factor', 'active',
])]
class Vehicle extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'powertrain' => Powertrain::class,
            'fuel_kind' => FuelKind::class,
            'consumption_l_100' => 'float',
            'consumption_kwh_100' => 'float',
            'battery_kwh_usable' => 'float',
            'regen_factor' => 'float',
            'thermal_efficiency' => 'float',
            'calibration_factor' => 'float',
            'active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Si no se especifican, los parámetros del modelo físico salen de la
        // tecnología. El usuario puede afinarlos después.
        static::saving(function (Vehicle $vehicle) {
            $vehicle->regen_factor ??= $vehicle->powertrain->defaultRegenFactor();

            if ($vehicle->powertrain->burnsFuel()) {
                $vehicle->thermal_efficiency ??= $vehicle->powertrain->defaultThermalEfficiency();
            } else {
                $vehicle->thermal_efficiency = null;
                $vehicle->fuel_kind = FuelKind::None;
            }
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function refuels(): HasMany
    {
        return $this->hasMany(Refuel::class);
    }

    /**
     * Desnivel máximo, en metros, que la batería puede absorber al descender
     * antes de saturarse. Es la razón por la que un híbrido no enchufable no
     * recupera un puerto de montaña entero: llena su batería en los primeros
     * cientos de metros y el resto lo disipa en los frenos.
     */
    public function regenCeilingMetres(float $massKg): float
    {
        if (! $this->powertrain->hasBattery() || ! $this->battery_kwh_usable) {
            return 0.0;
        }

        $gravity = (float) config('trayectos.physics.gravity');
        $chargeEfficiency = (float) config('trayectos.physics.regen_charge_efficiency');

        return ($this->battery_kwh_usable * 3.6e6 * $chargeEfficiency) / ($massKg * $gravity);
    }

    public function consumptionLabel(): string
    {
        return match ($this->powertrain) {
            Powertrain::Electric => number_format($this->consumption_kwh_100, 1, ',', '.').' kWh/100 km',
            Powertrain::PluginHybrid => number_format($this->consumption_l_100, 1, ',', '.').' L + '
                .number_format($this->consumption_kwh_100, 1, ',', '.').' kWh/100 km',
            default => number_format($this->consumption_l_100, 1, ',', '.').' L/100 km',
        };
    }
}
