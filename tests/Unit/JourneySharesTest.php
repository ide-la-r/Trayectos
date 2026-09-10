<?php

namespace Tests\Unit;

use App\Services\Ledger\JourneyShares;
use PHPUnit\Framework\TestCase;

/**
 * El reparto por tramos.
 *
 * Antes se repartía proporcional al trozo que hacía cada uno, y eso cobra de
 * menos a quien va todo el viaje: el trozo que hizo él solo es suyo entero.
 */
class JourneySharesTest extends TestCase
{
    private JourneyShares $journey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->journey = new JourneyShares;
    }

    /** @param array<int, float> $esperado */
    private function assertShares(array $esperado, array $presencia): void
    {
        $partes = $this->journey->fractions($presencia);

        foreach ($esperado as $id => $valor) {
            $this->assertEqualsWithDelta($valor, $partes[$id] ?? 0.0, 0.0001, "miembro $id");
        }

        $this->assertCount(count($esperado), $partes);
    }

    public function test_si_todos_hacen_el_viaje_entero_se_parte_a_partes_iguales(): void
    {
        // El caso normal, y tiene que seguir dando lo mismo que antes
        $this->assertShares(
            [1 => 1 / 3, 2 => 1 / 3, 3 => 1 / 3],
            [1 => 1.0, 2 => 1.0, 3 => 1.0],
        );
    }

    public function test_quien_se_baja_a_mitad_paga_su_mitad_compartida(): void
    {
        /*
         * Ana entera, Bea la mitad. La primera mitad la hicieron las dos, la
         * segunda Ana sola:
         *   0 → 0,5  entre dos  = 0,25 cada una
         *   0,5 → 1  Ana sola   = 0,50 para Ana
         *
         * El reparto proporcional daba 0,667 y 0,333: le cobraba de más a Bea
         * y de menos a Ana.
         */
        $this->assertShares(
            [1 => 0.75, 2 => 0.25],
            [1 => 1.0, 2 => 0.5],
        );
    }

    public function test_tres_personas_que_se_van_bajando(): void
    {
        /*
         *   0 → 0,25  van tres  = 0,0833 cada uno
         *   0,25 → 0,5 van dos  = 0,125 cada uno
         *   0,5 → 1   va uno    = 0,5
         */
        $this->assertShares(
            [1 => 0.0833 + 0.125 + 0.5, 2 => 0.0833 + 0.125, 3 => 0.0833],
            [1 => 1.0, 2 => 0.5, 3 => 0.25],
        );
    }

    public function test_los_que_hacen_lo_mismo_pagan_lo_mismo(): void
    {
        // Dos que van todo y dos que van medio: el orden no puede influir
        $partes = $this->journey->fractions([1 => 1.0, 2 => 0.5, 3 => 1.0, 4 => 0.5]);

        $this->assertEqualsWithDelta($partes[1], $partes[3], 0.0001);
        $this->assertEqualsWithDelta($partes[2], $partes[4], 0.0001);
        $this->assertEqualsWithDelta(1.0, array_sum($partes), 0.0001);
    }

    public function test_el_orden_en_que_se_apuntan_no_cambia_nada(): void
    {
        $primero = $this->journey->fractions([1 => 1.0, 2 => 0.5, 3 => 0.25]);
        $despues = $this->journey->fractions([3 => 0.25, 1 => 1.0, 2 => 0.5]);

        foreach ($primero as $id => $parte) {
            $this->assertEqualsWithDelta($parte, $despues[$id], 0.0001, "miembro $id");
        }
    }

    public function test_si_nadie_hace_el_viaje_entero_queda_una_parte_sin_cobrar(): void
    {
        /*
         * Pasa cuando el conductor no paga su parte: se le quita de la lista y
         * el tramo en el que iba solo no es de nadie. Se lo come él, que para
         * eso lo ha decidido el grupo. Por eso esto NO suma uno.
         */
        $partes = $this->journey->fractions([2 => 0.5]);

        $this->assertEqualsWithDelta(0.5, $partes[2], 0.0001);
        $this->assertEqualsWithDelta(0.5, array_sum($partes), 0.0001);
    }

    public function test_uno_solo_que_va_entero_lo_paga_todo(): void
    {
        $this->assertShares([2 => 1.0], [2 => 1.0]);
    }

    public function test_a_quien_no_iba_no_se_le_cobra(): void
    {
        $this->assertShares([1 => 1.0], [1 => 1.0, 2 => 0.0]);
    }

    public function test_sin_nadie_no_hay_nada_que_repartir(): void
    {
        $this->assertSame([], $this->journey->fractions([]));
    }
}
