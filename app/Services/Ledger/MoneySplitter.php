<?php

declare(strict_types=1);

namespace App\Services\Ledger;

/**
 * Reparte un importe en céntimos entre varios miembros con pesos.
 *
 * Dos exigencias que parecen menores y no lo son: la suma de las partes tiene
 * que dar exactamente el total (si no, el asiento no cuadra), y el reparto de
 * los céntimos sobrantes tiene que ser determinista y rotar — si el sobrante
 * cayera siempre en el mismo, esa persona pagaría de más sistemáticamente.
 */
final class MoneySplitter
{
    /**
     * @param  array<int|string, float>  $weights  [memberId => peso]
     * @return array<int|string, int> [memberId => céntimos]
     */
    public function split(int $totalCents, array $weights, int $seed = 0): array
    {
        $weights = array_filter($weights, fn (float $weight) => $weight > 0);

        if ($weights === []) {
            return [];
        }

        $totalWeight = array_sum($weights);
        $shares = [];
        $remainders = [];
        $assigned = 0;

        foreach ($weights as $memberId => $weight) {
            $exact = $totalCents * ($weight / $totalWeight);
            $floor = (int) floor($exact);

            $shares[$memberId] = $floor;
            $remainders[$memberId] = $exact - $floor;
            $assigned += $floor;
        }

        $leftover = $totalCents - $assigned;

        if ($leftover === 0) {
            return $shares;
        }

        // Mayor resto primero; los empates se rompen con un orden rotado por la
        // semilla (normalmente el id del viaje), no siempre por el mismo miembro.
        $order = array_keys($shares);
        sort($order);

        $rotation = $seed % count($order);
        $order = array_merge(array_slice($order, $rotation), array_slice($order, 0, $rotation));

        // Posición en el orden rotado: es el criterio de desempate estable
        $position = array_flip($order);

        usort($order, function ($a, $b) use ($remainders, $position) {
            return ($remainders[$b] <=> $remainders[$a])
                ?: ($position[$a] <=> $position[$b]);
        });

        $step = $leftover > 0 ? 1 : -1;

        for ($i = 0; $i < abs($leftover); $i++) {
            $shares[$order[$i % count($order)]] += $step;
        }

        return $shares;
    }
}
