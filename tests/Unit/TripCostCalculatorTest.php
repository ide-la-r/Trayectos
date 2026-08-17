<?php

namespace Tests\Unit;

use App\Enums\FuelKind;
use App\Enums\Powertrain;
use App\Models\Vehicle;
use App\Services\Costing\TripCostCalculator;
use App\Services\Costing\TripCostRequest;
use Tests\TestCase;

class TripCostCalculatorTest extends TestCase
{
    private TripCostCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new TripCostCalculator;
    }

    /** Vehículo en memoria: estos tests no tocan la base de datos. */
    private function vehicle(array $attributes = []): Vehicle
    {
        $vehicle = new Vehicle(array_merge([
            'label' => 'Coche de pruebas',
            'powertrain' => Powertrain::Combustion,
            'fuel_kind' => FuelKind::Gasoline95,
            'seats' => 5,
            'kerb_weight_kg' => 1500,
            'consumption_l_100' => 6.5,
            'regen_factor' => 0.05,
            'thermal_efficiency' => 0.25,
            'calibration_factor' => 1.0,
        ], $attributes));

        $vehicle->id = 1;

        return $vehicle;
    }

    private function request(Vehicle $vehicle, array $overrides = []): TripCostRequest
    {
        return new TripCostRequest(
            vehicle: $vehicle,
            distanceM: $overrides['distanceM'] ?? 60_000,
            ascentM: $overrides['ascentM'] ?? 0,
            descentM: $overrides['descentM'] ?? 0,
            occupants: $overrides['occupants'] ?? 4,
            fuelPriceMilli: $overrides['fuelPriceMilli'] ?? 1550,
            energyPriceMilli: $overrides['energyPriceMilli'] ?? 200,
            luggageKg: $overrides['luggageKg'] ?? 0,
            batteryStartPct: $overrides['batteryStartPct'] ?? 0,
        );
    }

    public function test_flat_route_costs_consumption_times_price(): void
    {
        $result = $this->calculator->calculate($this->request($this->vehicle()));

        // 6,5 L/100 × 60 km = 3,9 L × 1,550 €/L = 6,045 €
        $this->assertSame(3.9, round($result->litres, 3));
        $this->assertSame(605, $result->totalCents);
        $this->assertSame($result->flatCostCents, $result->totalCents);
    }

    public function test_climb_adds_gravitational_energy_to_a_combustion_engine(): void
    {
        $result = $this->calculator->calculate(
            $this->request($this->vehicle(), ['ascentM' => 900, 'descentM' => 120])
        );

        // m = 1500 + 4×75 = 1800 kg · desnivel efectivo = 900 − 0,05×120 = 894 m
        // E = 1800 × 9,81 × 894 = 15,79 MJ → /(0,25 × 32 MJ/L) = 1,973 L
        $this->assertEqualsWithDelta(5.873, $result->litres, 0.01);
        $this->assertEqualsWithDelta(50.6, $result->hillSurchargePercent(), 1.0);
        $this->assertSame(1800.0, $result->massKg);
    }

    public function test_hybrid_recovers_far_more_of_the_descent_than_combustion(): void
    {
        // Mismo puerto de ida y vuelta: sube 900 y baja 900
        $trip = ['ascentM' => 900, 'descentM' => 900];

        $combustion = $this->calculator->calculate($this->request($this->vehicle(), $trip));

        $hybrid = $this->calculator->calculate($this->request($this->vehicle([
            'powertrain' => Powertrain::Hybrid,
            'kerb_weight_kg' => 1600,
            'consumption_l_100' => 4.5,
            'battery_kwh_usable' => 1.3,
            'regen_factor' => 0.60,
            'thermal_efficiency' => 0.33,
        ]), $trip));

        // El térmico apenas descuenta la bajada (r = 0,05): se queda en 855 m
        // efectivos de los 900. El híbrido devuelve a la batería el 60 % de lo
        // que puede absorber y baja a unos 765 m.
        $this->assertEqualsWithDelta(855.0, $combustion->effectiveRiseM, 1.0);
        $this->assertLessThan($combustion->effectiveRiseM, $hybrid->effectiveRiseM);
        $this->assertLessThan($combustion->litres, $hybrid->litres);

        // Contraintuitivo pero correcto: el recargo PORCENTUAL del híbrido es
        // mayor, porque su consumo base es mucho menor y el mismo desnivel pesa
        // proporcionalmente más. Lo que baja es el gasto absoluto, no el %.
        $this->assertGreaterThan($combustion->hillSurchargePercent(), $hybrid->hillSurchargePercent());
    }

    public function test_hybrid_battery_saturates_on_a_long_descent(): void
    {
        $hybrid = $this->vehicle([
            'powertrain' => Powertrain::Hybrid,
            'kerb_weight_kg' => 1600,
            'consumption_l_100' => 4.5,
            'battery_kwh_usable' => 1.3,
            'regen_factor' => 0.60,
            'thermal_efficiency' => 0.33,
        ]);

        $short = $this->calculator->calculate($this->request($hybrid, ['ascentM' => 0, 'descentM' => 200]));
        $long = $this->calculator->calculate($this->request($hybrid, ['ascentM' => 0, 'descentM' => 2000]));

        // Bajar 2.000 m no recupera diez veces más que bajar 200: la batería
        // (1,3 kWh) se llena en unos 230 m y el resto se disipa en los frenos.
        $this->assertEqualsWithDelta($short->usableDescentM, $long->usableDescentM, 40.0);
        $this->assertGreaterThan(200.0, $long->regenCeilingM);
        $this->assertLessThan(300.0, $long->regenCeilingM);
    }

    public function test_consumption_never_falls_below_the_floor_going_downhill(): void
    {
        // Un eléctrico bajando 3.000 m recuperaría, sobre el papel, más energía
        // de la que gasta en mover el coche. En la calle eso no ocurre: quedan
        // accesorios, climatización y tramos llanos. El suelo del 40 % lo impide.
        $result = $this->calculator->calculate($this->request($this->vehicle([
            'powertrain' => Powertrain::Electric,
            'fuel_kind' => FuelKind::None,
            'kerb_weight_kg' => 1900,
            'consumption_l_100' => null,
            'consumption_kwh_100' => 17.0,
            'battery_kwh_usable' => 60.0,
            'ev_range_km' => 350,
            'regen_factor' => 0.70,
            'thermal_efficiency' => null,
        ]), ['ascentM' => 0, 'descentM' => 3000]));

        $this->assertEqualsWithDelta(10.2 * 0.40, $result->kwh, 0.001);
        $this->assertGreaterThan(0, $result->totalCents);
    }

    public function test_electric_vehicle_is_charged_in_kwh_and_never_in_litres(): void
    {
        $result = $this->calculator->calculate($this->request($this->vehicle([
            'powertrain' => Powertrain::Electric,
            'fuel_kind' => FuelKind::None,
            'kerb_weight_kg' => 1900,
            'consumption_l_100' => null,
            'consumption_kwh_100' => 17.0,
            'battery_kwh_usable' => 60.0,
            'ev_range_km' => 350,
            'regen_factor' => 0.70,
            'thermal_efficiency' => null,
        ]), ['ascentM' => 900, 'descentM' => 120, 'energyPriceMilli' => 245]));

        $this->assertSame(0.0, $result->litres);
        $this->assertGreaterThan(10.2, $result->kwh);  // 17 kWh/100 × 60 km = 10,2 base
        $this->assertSame(1.0, $result->evShare);
    }

    public function test_plugin_hybrid_splits_the_trip_between_battery_and_fuel(): void
    {
        $phev = $this->vehicle([
            'powertrain' => Powertrain::PluginHybrid,
            'kerb_weight_kg' => 1800,
            'consumption_l_100' => 5.8,
            'consumption_kwh_100' => 18.0,
            'battery_kwh_usable' => 13.0,
            'ev_range_km' => 50,
            'regen_factor' => 0.65,
            'thermal_efficiency' => 0.32,
        ]);

        // 100 km con la batería llena: 50 km eléctricos y 50 km térmicos
        $result = $this->calculator->calculate(
            $this->request($phev, ['distanceM' => 100_000, 'batteryStartPct' => 100])
        );

        $this->assertSame(0.5, round($result->evShare, 3));
        $this->assertEqualsWithDelta(2.9, $result->litres, 0.01);   // 5,8/100 × 50 km
        $this->assertEqualsWithDelta(9.0, $result->kwh, 0.01);      // 18/100 × 50 km

        // Con la batería vacía el mismo trayecto es 100 % térmico
        $empty = $this->calculator->calculate(
            $this->request($phev, ['distanceM' => 100_000, 'batteryStartPct' => 0])
        );

        $this->assertSame(0.0, $empty->evShare);
        $this->assertSame(0.0, $empty->kwh);
        $this->assertEqualsWithDelta(5.8, $empty->litres, 0.01);
    }

    public function test_extra_passengers_and_luggage_increase_the_cost_of_a_climb(): void
    {
        $light = $this->calculator->calculate(
            $this->request($this->vehicle(), ['ascentM' => 800, 'occupants' => 1])
        );

        $loaded = $this->calculator->calculate(
            $this->request($this->vehicle(), ['ascentM' => 800, 'occupants' => 5, 'luggageKg' => 60])
        );

        $this->assertGreaterThan($light->totalCents, $loaded->totalCents);
        $this->assertSame(1935.0, $loaded->massKg);   // 1500 + 5×75 + 60
    }

    public function test_calibration_factor_scales_the_whole_cost(): void
    {
        $base = $this->calculator->calculate($this->request($this->vehicle()));
        $calibrated = $this->calculator->calculate(
            $this->request($this->vehicle(['calibration_factor' => 1.2]))
        );

        // El factor se aplica antes de redondear a céntimos, de ahí la tolerancia
        $this->assertEqualsWithDelta($base->totalCents * 1.2, $calibrated->totalCents, 1);
    }

    public function test_zero_distance_costs_nothing(): void
    {
        $result = $this->calculator->calculate($this->request($this->vehicle(), ['distanceM' => 0]));

        $this->assertSame(0, $result->totalCents);
    }
}
