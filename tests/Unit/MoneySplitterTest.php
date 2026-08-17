<?php

namespace Tests\Unit;

use App\Services\Ledger\MoneySplitter;
use Tests\TestCase;

class MoneySplitterTest extends TestCase
{
    private MoneySplitter $splitter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->splitter = new MoneySplitter;
    }

    public function test_equal_split_adds_up_to_the_exact_total(): void
    {
        $shares = $this->splitter->split(2500, [1 => 1.0, 2 => 1.0, 3 => 1.0]);

        $this->assertSame(2500, array_sum($shares));
        $this->assertSame([834, 833, 833], array_values($shares));
    }

    public function test_weights_are_respected(): void
    {
        // Alguien que sólo hace la mitad del trayecto paga la mitad
        $shares = $this->splitter->split(3000, [1 => 1.0, 2 => 1.0, 3 => 0.5]);

        $this->assertSame(3000, array_sum($shares));
        $this->assertSame(1200, $shares[1]);
        $this->assertSame(1200, $shares[2]);
        $this->assertSame(600, $shares[3]);
    }

    public function test_leftover_cents_rotate_with_the_seed(): void
    {
        $first = $this->splitter->split(100, [1 => 1.0, 2 => 1.0, 3 => 1.0], seed: 0);
        $second = $this->splitter->split(100, [1 => 1.0, 2 => 1.0, 3 => 1.0], seed: 1);

        // Los dos reparten el total exacto…
        $this->assertSame(100, array_sum($first));
        $this->assertSame(100, array_sum($second));

        // …pero el céntimo sobrante no cae siempre en la misma persona
        $this->assertNotSame($first, $second);
    }

    public function test_split_is_deterministic_for_the_same_seed(): void
    {
        $weights = [7 => 1.0, 3 => 1.0, 11 => 1.0, 5 => 1.0];

        $this->assertSame(
            $this->splitter->split(1234, $weights, seed: 42),
            $this->splitter->split(1234, $weights, seed: 42),
        );
    }

    public function test_zero_and_negative_weights_are_ignored(): void
    {
        $shares = $this->splitter->split(1000, [1 => 1.0, 2 => 0.0, 3 => -1.0]);

        $this->assertSame([1 => 1000], $shares);
    }

    public function test_empty_weights_return_nothing(): void
    {
        $this->assertSame([], $this->splitter->split(1000, []));
    }

    public function test_single_payer_takes_everything(): void
    {
        $this->assertSame([9 => 777], $this->splitter->split(777, [9 => 1.0]));
    }
}
