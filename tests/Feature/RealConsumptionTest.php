<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Costing\CalibrationService;
use App\Services\Costing\RealConsumption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El consumo medido de lleno a lleno, y la calibración que sale de él.
 */
class RealConsumptionTest extends TestCase
{
    use RefreshDatabase;

    private Vehicle $vehicle;

    private GroupMember $driver;

    private Group $group;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group = Group::factory()->create();
        $this->owner = User::factory()->create();

        $this->driver = GroupMember::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $this->owner->id,
        ]);

        // La ficha de este coche dice 6,5 L/100
        $this->vehicle = Vehicle::factory()->create(['owner_id' => $this->owner->id]);
    }

    /** Un repostaje, con el cuentakilómetros de ese momento. */
    private function repostaje(int $odometer, ?float $litres, int $diaDe90, bool $lleno = true): void
    {
        $this->vehicle->refuels()->create([
            'refuelled_on' => now()->subDays(90)->addDays($diaDe90)->toDateString(),
            'litres' => $litres,
            'cost_cents' => (int) round(($litres ?? 0) * 155),
            'odometer_km' => $odometer,
            'full_tank' => $lleno,
        ]);
    }

    /** Un viaje apuntado, con lo que el modelo predijo entonces. */
    private function viaje(int $km, float $litrosPrevistos, int $diaDe90): Trip
    {
        return Trip::create([
            'group_id' => $this->group->id,
            'vehicle_id' => $this->vehicle->id,
            'driver_member_id' => $this->driver->id,
            'travelled_on' => now()->subDays(90)->addDays($diaDe90)->toDateString(),
            'origin_label' => 'Málaga',
            'destination_label' => 'Granada',
            'distance_m' => $km * 1000,
            'total_cost_cents' => 5000,
            'cost_inputs' => ['breakdown' => ['litres' => $litrosPrevistos, 'kwh' => 0]],
            'formula_version' => 1,
        ]);
    }

    public function test_dos_llenados_completos_dicen_lo_que_gasta_el_coche(): void
    {
        // 65 litros para mil kilómetros son 6,5 cada cien. Sin modelo físico de
        // por medio: el depósito estaba lleno y se ha vuelto a llenar.
        $this->repostaje(odometer: 10_000, litres: 40, diaDe90: 0);
        $this->repostaje(odometer: 11_000, litres: 65, diaDe90: 20);

        $medida = app(RealConsumption::class)->average($this->vehicle);

        $this->assertSame(1, $medida['tanks']);
        $this->assertSame(1000, $medida['km']);
        $this->assertSame(6.5, $medida['litres']);
    }

    public function test_el_primer_repostaje_no_mide_nada_por_si_solo(): void
    {
        // No se sabe con qué depósito se llegó hasta él
        $this->repostaje(odometer: 10_000, litres: 40, diaDe90: 0);

        $medida = app(RealConsumption::class)->average($this->vehicle);

        $this->assertSame(0, $medida['tanks']);
        $this->assertNull($medida['litres']);
    }

    public function test_un_repostaje_parcial_de_en_medio_tambien_entro_en_el_deposito(): void
    {
        /*
         * Veinte litros a mitad de camino y cuarenta y cinco al llenar son los
         * sesenta y cinco que se han gastado. Contar sólo los del último
         * llenado diría que el coche hace 4,5 y sería mentira.
         */
        $this->repostaje(odometer: 10_000, litres: 40, diaDe90: 0);
        $this->repostaje(odometer: 10_500, litres: 20, diaDe90: 10, lleno: false);
        $this->repostaje(odometer: 11_000, litres: 45, diaDe90: 20);

        $medida = app(RealConsumption::class)->average($this->vehicle);

        $this->assertSame(1, $medida['tanks']);
        $this->assertSame(6.5, $medida['litres']);
    }

    public function test_un_cuentakilometros_mal_tecleado_no_entra_en_la_media(): void
    {
        // Un cero de más: 110.000 en vez de 11.000. Serían cien mil kilómetros
        // con sesenta y cinco litros, y esa media se lleva por delante el resto.
        $this->repostaje(odometer: 10_000, litres: 40, diaDe90: 0);
        $this->repostaje(odometer: 110_000, litres: 65, diaDe90: 20);
        $this->repostaje(odometer: 111_000, litres: 65, diaDe90: 40);

        $medida = app(RealConsumption::class)->average($this->vehicle);

        // Se descarta el depósito imposible, no el que viene detrás
        $this->assertSame(1, $medida['tanks']);
        $this->assertSame(6.5, $medida['litres']);
    }

    public function test_los_kilometros_sin_apuntar_ya_no_inflan_el_factor(): void
    {
        /*
         * Éste es el fallo que traía la calibración anterior. El coche gasta
         * exactamente lo que dice su ficha, pero de los 2.000 km conducidos
         * sólo hay 600 apuntados como viajes: el resto es ir a trabajar y la
         * compra, que no los apunta nadie.
         *
         * La cuenta vieja dividía TODO lo repostado entre lo previsto para esos
         * 600 km —170 entre 39, o sea 4,36— y se clavaba en el tope de 1,350: un
         * 35 % de más en todos los viajes siguientes por no apuntar la compra.
         */
        $this->repostaje(odometer: 10_000, litres: 40, diaDe90: 0);
        $this->repostaje(odometer: 11_000, litres: 65, diaDe90: 20);
        $this->repostaje(odometer: 12_000, litres: 65, diaDe90: 40);

        $this->viaje(km: 600, litrosPrevistos: 39.0, diaDe90: 10);

        $resultado = app(CalibrationService::class)->recalculate($this->vehicle);

        $this->assertSame('calibrated', $resultado['status']);
        // 6,5 medidos contra 6,5 previstos: el modelo estaba bien
        $this->assertSame(6.5, $resultado['measured_per_100']);
        $this->assertSame(6.5, $resultado['model_per_100']);
        $this->assertSame(1.0, $resultado['factor']);
        $this->assertSame(1.0, $this->vehicle->fresh()->calibration_factor);
    }

    public function test_cuando_el_coche_gasta_de_verdad_mas_el_factor_sube(): void
    {
        // 78 litros por cada mil kilómetros son 7,8: un 20 % sobre los 6,5 que
        // predijo el modelo. Eso sí hay que corregirlo.
        $this->repostaje(odometer: 10_000, litres: 40, diaDe90: 0);
        $this->repostaje(odometer: 11_000, litres: 78, diaDe90: 20);
        $this->repostaje(odometer: 12_000, litres: 78, diaDe90: 40);

        $this->viaje(km: 600, litrosPrevistos: 39.0, diaDe90: 10);

        $resultado = app(CalibrationService::class)->recalculate($this->vehicle);

        $this->assertSame(7.8, $resultado['measured_per_100']);
        $this->assertSame(1.2, $resultado['factor']);
    }

    public function test_con_un_viaje_suelto_no_hay_muestra_que_valga(): void
    {
        $this->vehicle->forceFill(['calibration_factor' => 1.100])->save();

        $this->repostaje(odometer: 10_000, litres: 40, diaDe90: 0);
        $this->repostaje(odometer: 11_000, litres: 65, diaDe90: 20);
        $this->repostaje(odometer: 12_000, litres: 65, diaDe90: 40);

        // 100 km apuntados de 2.000 conducidos: el ritmo del modelo lo marca un
        // viaje suelto y no representa cómo se conduce este coche.
        $this->viaje(km: 100, litrosPrevistos: 6.5, diaDe90: 10);

        $resultado = app(CalibrationService::class)->recalculate($this->vehicle);

        $this->assertSame('thin_sample', $resultado['status']);
        $this->assertSame(0.05, $resultado['coverage']);
        // El consumo real sí se ha medido, aunque no se calibre
        $this->assertSame(6.5, $resultado['measured_per_100']);
        // Y el factor que había se queda como estaba
        $this->assertSame(1.1, $this->vehicle->fresh()->calibration_factor);
    }

    public function test_sin_cuentakilometros_no_se_puede_calibrar(): void
    {
        $this->vehicle->refuels()->create([
            'refuelled_on' => now()->subDays(30)->toDateString(),
            'litres' => 65,
            'cost_cents' => 10_000,
            'odometer_km' => null,
            'full_tank' => true,
        ]);

        $resultado = app(CalibrationService::class)->recalculate($this->vehicle);

        $this->assertSame('no_tanks', $resultado['status']);
        $this->assertSame(0, $resultado['tanks']);
        $this->assertSame(1.0, $this->vehicle->fresh()->calibration_factor);
    }

    public function test_la_pantalla_del_coche_ensena_el_consumo_real(): void
    {
        $this->repostaje(odometer: 10_000, litres: 40, diaDe90: 0);
        $this->repostaje(odometer: 11_000, litres: 65, diaDe90: 20);

        $this->actingAs($this->owner)
            ->get(route('vehicles.edit', $this->vehicle))
            ->assertOk()
            ->assertSee('Consumo real de este coche')
            // El número grande, y de dónde sale
            ->assertSee('6,5 L')
            ->assertSee('Medido en 1 depósito y 1.000 km')
            // Y en su fila, el depósito que lo midió
            ->assertSee('6,5 L/100 km en 1.000 km');
    }

    public function test_un_repostaje_sin_cuentakilometros_lo_dice_en_su_fila(): void
    {
        $this->vehicle->refuels()->create([
            'refuelled_on' => now()->subDays(10)->toDateString(),
            'litres' => 50,
            'cost_cents' => 8000,
            'odometer_km' => null,
            'full_tank' => true,
        ]);

        $this->actingAs($this->owner)
            ->get(route('vehicles.edit', $this->vehicle))
            ->assertOk()
            ->assertSee('Sin cuentakilómetros')
            ->assertDontSee('Consumo real de este coche');
    }

    public function test_al_apuntar_un_repostaje_te_dice_lo_que_gasta_de_verdad(): void
    {
        $this->repostaje(odometer: 10_000, litres: 40, diaDe90: 0);

        $this->actingAs($this->owner)
            ->post(route('refuels.store', $this->vehicle), [
                'refuelled_on' => now()->toDateString(),
                'litres' => '65',
                'cost' => '100',
                'odometer_km' => '11000',
                'full_tank' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', fn (string $mensaje) => str_contains($mensaje, '6,5 L/100 km de verdad'));
    }

    public function test_sin_cuentakilometros_el_aviso_pide_que_se_apunte(): void
    {
        $this->actingAs($this->owner)
            ->post(route('refuels.store', $this->vehicle), [
                'refuelled_on' => now()->toDateString(),
                'litres' => '65',
                'cost' => '100',
                'full_tank' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', fn (string $mensaje) => str_contains($mensaje, 'Apunta el cuentakilómetros'));
    }

    public function test_el_comando_del_cron_dice_que_le_falta_a_cada_coche(): void
    {
        /*
         * Lo corre el cron todas las noches. Antes decía «sin datos suficientes
         * todavía» y ahí se quedaba uno, sin saber si le faltaba apuntar el
         * cuentakilómetros, llenar otra vez o apuntar viajes.
         */
        $this->repostaje(odometer: 10_000, litres: 40, diaDe90: 0);
        $this->repostaje(odometer: 11_000, litres: 65, diaDe90: 20);
        $this->repostaje(odometer: 12_000, litres: 65, diaDe90: 40);

        $this->artisan('trayectos:calibrate')
            ->expectsOutputToContain('Sin viajes apuntados en ese periodo')
            ->assertSuccessful();

        // Con viajes que cubran los kilómetros, el mismo comando sí ajusta
        $this->viaje(km: 600, litrosPrevistos: 39.0, diaDe90: 10);

        $this->artisan('trayectos:calibrate')
            ->expectsOutputToContain('factor 1.000')
            ->assertSuccessful();
    }

    public function test_con_depositos_medidos_pero_ningun_viaje_apuntado_no_se_calibra(): void
    {
        $this->repostaje(odometer: 10_000, litres: 40, diaDe90: 0);
        $this->repostaje(odometer: 11_000, litres: 65, diaDe90: 20);
        $this->repostaje(odometer: 12_000, litres: 65, diaDe90: 40);

        $resultado = app(CalibrationService::class)->recalculate($this->vehicle);

        $this->assertSame('no_trips', $resultado['status']);
        // Pero el consumo real se sabe igual: para eso no hacen falta viajes
        $this->assertSame(6.5, $resultado['measured_per_100']);
        $this->assertSame(2000, $resultado['km']);
    }
}
