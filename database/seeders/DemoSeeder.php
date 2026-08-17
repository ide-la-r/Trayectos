<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\FuelKind;
use App\Enums\Powertrain;
use App\Models\EnergyPrice;
use App\Models\FuelPrice;
use App\Models\FuelStation;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Trips\TripDraft;
use App\Services\Trips\TripRecorder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Grupo de ejemplo con viajes reales de la sierra de Madrid, para ver el sistema
 * funcionando sin tener que apuntar nada a mano. La contraseña de todos es
 * 'trayectos'.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $people = ['Ana', 'Bea', 'Carlos', 'Diego'];
        $members = [];

        $creator = null;

        foreach ($people as $name) {
            $user = User::updateOrCreate(
                ['email' => mb_strtolower($name).'@ejemplo.es'],
                ['name' => $name, 'password' => Hash::make('trayectos'), 'email_verified_at' => now()],
            );

            $creator ??= $user;
            $members[$name] = $user;
        }

        $group = Group::updateOrCreate(
            ['invite_code' => 'SIERRA26'],
            [
                'name' => 'Los del jueves',
                'driver_pays_own_share' => true,
                'created_by' => $creator->id,
            ],
        );

        $groupMembers = [];

        foreach ($members as $name => $user) {
            $groupMembers[$name] = GroupMember::updateOrCreate(
                ['group_id' => $group->id, 'user_id' => $user->id],
                ['role' => $name === 'Ana' ? 'admin' : 'member', 'active' => true],
            );
        }

        // ─── Un coche de cada tecnología, para comparar en el mismo grupo ────
        $vehicles = [
            'Ana' => [
                'label' => 'Golf de Ana',
                'powertrain' => Powertrain::Combustion,
                'fuel_kind' => FuelKind::Gasoline95,
                'kerb_weight_kg' => 1350,
                'consumption_l_100' => 6.4,
                'seats' => 5,
            ],
            'Bea' => [
                'label' => 'Corolla híbrido de Bea',
                'powertrain' => Powertrain::Hybrid,
                'fuel_kind' => FuelKind::Gasoline95,
                'kerb_weight_kg' => 1420,
                'consumption_l_100' => 4.3,
                'battery_kwh_usable' => 1.3,
                'seats' => 5,
            ],
            'Carlos' => [
                'label' => 'Passat diésel de Carlos',
                'powertrain' => Powertrain::Combustion,
                'fuel_kind' => FuelKind::Diesel,
                'kerb_weight_kg' => 1550,
                'consumption_l_100' => 5.1,
                'thermal_efficiency' => 0.30,
                'seats' => 5,
            ],
            'Diego' => [
                'label' => 'Model 3 de Diego',
                'powertrain' => Powertrain::Electric,
                'fuel_kind' => FuelKind::None,
                'kerb_weight_kg' => 1830,
                'consumption_kwh_100' => 16.5,
                'battery_kwh_usable' => 57.5,
                'ev_range_km' => 430,
                'seats' => 5,
            ],
        ];

        $cars = [];

        foreach ($vehicles as $owner => $attributes) {
            $cars[$owner] = Vehicle::updateOrCreate(
                ['owner_id' => $members[$owner]->id, 'label' => $attributes['label']],
                $attributes,
            );
        }

        $this->seedPrices();

        // ─── Viajes: sierra, aeropuerto y un puerto de montaña ──────────────
        $trips = [
            [
                'driver' => 'Ana',
                'car' => 'Ana',
                'from' => ['Madrid', 40.416775, -3.703790],
                'to' => ['Puerto de Navacerrada', 40.786944, -4.003889],
                'passengers' => ['Ana', 'Bea', 'Carlos', 'Diego'],
                'days_ago' => 21,
                'round' => true,
            ],
            [
                'driver' => 'Bea',
                'car' => 'Bea',
                'from' => ['Madrid', 40.416775, -3.703790],
                'to' => ['Toledo', 39.862833, -4.027323],
                'passengers' => ['Bea', 'Ana', 'Diego'],
                'days_ago' => 14,
                'round' => true,
            ],
            [
                'driver' => 'Carlos',
                'car' => 'Carlos',
                'from' => ['Madrid', 40.416775, -3.703790],
                'to' => ['Aeropuerto T4', 40.491389, -3.593611],
                'passengers' => ['Carlos', 'Bea'],
                'days_ago' => 9,
                'round' => false,
            ],
            [
                'driver' => 'Diego',
                'car' => 'Diego',
                'from' => ['Madrid', 40.416775, -3.703790],
                'to' => ['Segovia', 40.942903, -4.108807],
                'passengers' => ['Diego', 'Ana', 'Bea', 'Carlos'],
                'days_ago' => 4,
                'round' => true,
            ],
        ];

        $recorder = app(TripRecorder::class);

        foreach ($trips as $index => $trip) {
            $weights = [];

            foreach ($trip['passengers'] as $name) {
                $weights[$groupMembers[$name]->id] = 1.0;
            }

            $recorder->record(new TripDraft(
                group: $group,
                vehicle: $cars[$trip['car']],
                driver: $groupMembers[$trip['driver']],
                travelledOn: now()->subDays($trip['days_ago']),
                originLabel: $trip['from'][0],
                destinationLabel: $trip['to'][0],
                passengerWeights: $weights,
                originLat: $trip['from'][1],
                originLon: $trip['from'][2],
                destinationLat: $trip['to'][1],
                destinationLon: $trip['to'][2],
                roundTrip: $trip['round'],
                createdBy: $members[$trip['driver']]->id,
            ));

            $this->command?->line("· viaje {$trip['from'][0]} → {$trip['to'][0]} apuntado");
        }

        $this->command?->info("Grupo «{$group->name}» listo. Entra con ana@ejemplo.es / trayectos");
    }

    /**
     * Precios de referencia por si no se ha corrido todavía la sincronización
     * con el Ministerio. Los reales llegan con `trayectos:sync-prices`.
     */
    private function seedPrices(): void
    {
        if (FuelPrice::exists()) {
            return;
        }

        $station = FuelStation::updateOrCreate(
            ['ideess' => 999999],
            [
                'label' => 'Gasolinera de ejemplo',
                'municipality' => 'Madrid',
                'province' => 'MADRID',
                'province_id' => '28',
                'lat' => 40.4168,
                'lon' => -3.7038,
            ],
        );

        foreach ([[FuelKind::Gasoline95, 1749], [FuelKind::Diesel, 1689]] as [$kind, $priceMilli]) {
            FuelPrice::updateOrCreate(
                [
                    'fuel_station_id' => $station->id,
                    'fuel_kind' => $kind,
                    'observed_at' => now()->startOfDay(),
                ],
                ['price_milli' => $priceMilli],
            );
        }

        EnergyPrice::updateOrCreate(
            ['source' => 'manual', 'observed_at' => now()->startOfDay()],
            ['price_milli' => 150],
        );
    }
}
