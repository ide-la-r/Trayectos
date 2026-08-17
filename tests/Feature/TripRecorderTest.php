<?php

namespace Tests\Feature;

use App\Models\FuelPrice;
use App\Models\FuelStation;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Ledger\BalanceService;
use App\Services\Trips\TripDraft;
use App\Services\Trips\TripRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TripRecorderTest extends TestCase
{
    use RefreshDatabase;

    private Group $group;

    private GroupMember $driver;

    private GroupMember $passenger;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('trayectos.ors.key', 'clave-de-pruebas');

        $this->group = Group::factory()->create();

        $driverUser = User::factory()->create(['name' => 'Ana']);
        $this->driver = GroupMember::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $driverUser->id,
        ]);

        $this->passenger = GroupMember::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => User::factory()->create(['name' => 'Bea'])->id,
        ]);

        $this->vehicle = Vehicle::factory()->create(['owner_id' => $driverUser->id]);

        $this->seedFuelPrice();
    }

    private function seedFuelPrice(int $priceMilli = 1550): void
    {
        $station = FuelStation::create([
            'ideess' => 4982,
            'label' => 'CEPSA',
            'municipality' => 'Adanero',
            'lat' => 40.955111,
            'lon' => -4.614556,
        ]);

        FuelPrice::create([
            'fuel_station_id' => $station->id,
            'fuel_kind' => 'G95E5',
            'price_milli' => $priceMilli,
            'observed_at' => now(),
        ]);
    }

    private function fakeOrs(int $distanceM = 60000, int $ascent = 900, int $descent = 120): void
    {
        Http::fake([
            '*openrouteservice*' => Http::response([
                'features' => [[
                    'properties' => [
                        'summary' => ['distance' => $distanceM, 'duration' => 3300],
                        'ascent' => $ascent,
                        'descent' => $descent,
                    ],
                    'geometry' => [
                        'coordinates' => [[-4.61, 40.95, 900], [-4.0, 40.78, 1100]],
                    ],
                ]],
            ]),
        ]);
    }

    private function draft(array $overrides = []): TripDraft
    {
        return new TripDraft(
            group: $this->group,
            vehicle: $this->vehicle,
            driver: $this->driver,
            travelledOn: now(),
            originLabel: 'Adanero',
            destinationLabel: 'Navacerrada',
            passengerWeights: $overrides['passengerWeights'] ?? [
                $this->driver->id => 1.0,
                $this->passenger->id => 1.0,
            ],
            originLat: $overrides['originLat'] ?? 40.955111,
            originLon: $overrides['originLon'] ?? -4.614556,
            destinationLat: $overrides['destinationLat'] ?? 40.780000,
            destinationLon: $overrides['destinationLon'] ?? -4.003000,
            roundTrip: $overrides['roundTrip'] ?? false,
            manualDistanceM: $overrides['manualDistanceM'] ?? null,
            manualAscentM: $overrides['manualAscentM'] ?? null,
            manualDescentM: $overrides['manualDescentM'] ?? null,
        );
    }

    public function test_it_records_a_trip_with_route_price_cost_and_ledger_entry(): void
    {
        $this->fakeOrs();

        $trip = app(TripRecorder::class)->record($this->draft());

        $this->assertSame(60_000, $trip->distance_m);
        $this->assertSame(900, $trip->ascent_m);
        $this->assertSame('ors', $trip->route_source);
        $this->assertGreaterThan(0, $trip->total_cost_cents);
        $this->assertNotNull($trip->journal_entry_id);

        // El snapshot guarda de dónde salió cada número
        $this->assertSame(1550, data_get($trip->cost_inputs, 'inputs.fuel_price_milli'));
        $this->assertSame('station', data_get($trip->cost_inputs, 'fuel_price.source'));
        $this->assertSame(3, $trip->formula_version);

        // Y el reparto cuadra: media parte para cada uno, con el céntimo
        // sobrante asignado de forma determinista a uno de los dos
        $balances = app(BalanceService::class);
        $passengerShare = -$balances->forMember($this->passenger);

        $this->assertEqualsWithDelta($trip->total_cost_cents / 2, $passengerShare, 1);
        $this->assertSame($passengerShare, $balances->forMember($this->driver));
        $this->assertTrue($balances->isConsistent($this->group));
    }

    public function test_a_round_trip_doubles_distance_and_crosses_the_elevation(): void
    {
        $this->fakeOrs(distanceM: 50_000, ascent: 800, descent: 100);

        $trip = app(TripRecorder::class)->record($this->draft(['roundTrip' => true]));

        $this->assertSame(100_000, $trip->distance_m);
        // Lo que se sube a la ida se baja a la vuelta: los acumulados se cruzan
        $this->assertSame(900, $trip->ascent_m);
        $this->assertSame(900, $trip->descent_m);
    }

    public function test_manual_values_take_precedence_over_the_routing_api(): void
    {
        $this->fakeOrs();

        $trip = app(TripRecorder::class)->record($this->draft([
            'manualDistanceM' => 42_000,
            'manualAscentM' => 300,
            'manualDescentM' => 250,
        ]));

        $this->assertSame(42_000, $trip->distance_m);
        $this->assertSame('manual', $trip->route_source);
        Http::assertNothingSent();
    }

    public function test_it_degrades_to_a_straight_line_estimate_when_ors_fails(): void
    {
        Http::fake([
            '*openrouteservice*' => Http::response(['error' => 'quota exceeded'], 403),
            '*opentopodata*' => Http::response([
                'status' => 'OK',
                'results' => [
                    ['elevation' => 900], ['elevation' => 1000], ['elevation' => 1400],
                ],
            ]),
        ]);

        $trip = app(TripRecorder::class)->record($this->draft());

        // Que se agote una cuota gratuita no puede impedir apuntar el viaje
        $this->assertSame('haversine', $trip->route_source);
        $this->assertGreaterThan(0, $trip->distance_m);
        $this->assertSame(500, $trip->ascent_m);   // 900 → 1400 según el perfil falso
        $this->assertNotNull($trip->journal_entry_id);
        $this->assertNotNull(data_get($trip->cost_inputs, 'route.warning'));
    }

    public function test_it_falls_back_to_the_configured_price_without_local_data(): void
    {
        FuelPrice::query()->delete();
        $this->fakeOrs();

        $trip = app(TripRecorder::class)->record($this->draft());

        $this->assertSame('fallback', data_get($trip->cost_inputs, 'fuel_price.source'));
        $this->assertSame(
            config('trayectos.fallback_prices.fuel_milli.G95E5'),
            data_get($trip->cost_inputs, 'inputs.fuel_price_milli')
        );
    }
}
