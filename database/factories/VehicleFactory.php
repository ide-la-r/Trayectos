<?php

namespace Database\Factories;

use App\Enums\FuelKind;
use App\Enums\Powertrain;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Vehicle> */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'label' => 'Coche de '.fake()->firstName(),
            'powertrain' => Powertrain::Combustion,
            'fuel_kind' => FuelKind::Gasoline95,
            'seats' => 5,
            'kerb_weight_kg' => 1500,
            'consumption_l_100' => 6.50,
            'consumption_kwh_100' => null,
            'battery_kwh_usable' => null,
            'ev_range_km' => null,
            'regen_factor' => Powertrain::Combustion->defaultRegenFactor(),
            'thermal_efficiency' => Powertrain::Combustion->defaultThermalEfficiency(),
            'calibration_factor' => 1.000,
            'active' => true,
        ];
    }

    public function diesel(): static
    {
        return $this->state(fn () => [
            'fuel_kind' => FuelKind::Diesel,
            'consumption_l_100' => 5.20,
            'thermal_efficiency' => 0.30,
        ]);
    }

    public function hybrid(): static
    {
        return $this->state(fn () => [
            'powertrain' => Powertrain::Hybrid,
            'fuel_kind' => FuelKind::Gasoline95,
            'kerb_weight_kg' => 1600,
            'consumption_l_100' => 4.50,
            'battery_kwh_usable' => 1.30,
            'regen_factor' => Powertrain::Hybrid->defaultRegenFactor(),
            'thermal_efficiency' => Powertrain::Hybrid->defaultThermalEfficiency(),
        ]);
    }

    public function pluginHybrid(): static
    {
        return $this->state(fn () => [
            'powertrain' => Powertrain::PluginHybrid,
            'fuel_kind' => FuelKind::Gasoline95,
            'kerb_weight_kg' => 1800,
            'consumption_l_100' => 5.80,
            'consumption_kwh_100' => 18.00,
            'battery_kwh_usable' => 13.00,
            'ev_range_km' => 55,
            'regen_factor' => Powertrain::PluginHybrid->defaultRegenFactor(),
            'thermal_efficiency' => Powertrain::PluginHybrid->defaultThermalEfficiency(),
        ]);
    }

    public function electric(): static
    {
        return $this->state(fn () => [
            'powertrain' => Powertrain::Electric,
            'fuel_kind' => FuelKind::None,
            'kerb_weight_kg' => 1900,
            'consumption_l_100' => null,
            'consumption_kwh_100' => 17.00,
            'battery_kwh_usable' => 60.00,
            'ev_range_km' => 350,
            'regen_factor' => Powertrain::Electric->defaultRegenFactor(),
            'thermal_efficiency' => null,
        ]);
    }
}
