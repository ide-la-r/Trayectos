<?php

declare(strict_types=1);

namespace App\Services\Costing;

/**
 * Resultado del cálculo. Se serializa entero en trips.cost_inputs: sin ese
 * snapshot, recalibrar un vehículo cambiaría el coste de viajes ya asentados.
 */
final class TripCostBreakdown
{
    public function __construct(
        public readonly int $totalCents,
        public readonly int $flatCostCents,     // el mismo trayecto sin desnivel
        public readonly float $litres,
        public readonly float $kwh,
        public readonly float $massKg,
        public readonly float $effectiveRiseM,  // ascenso neto tras la regeneración
        public readonly float $regenCeilingM,
        public readonly float $usableDescentM,
        public readonly float $evShare,         // fracción del trayecto en eléctrico
        public readonly array $inputs,
    ) {}

    public function hillSurchargeCents(): int
    {
        return $this->totalCents - $this->flatCostCents;
    }

    public function hillSurchargePercent(): float
    {
        return $this->flatCostCents > 0
            ? round(($this->hillSurchargeCents() / $this->flatCostCents) * 100, 1)
            : 0.0;
    }

    /** Snapshot inmutable que se guarda con el viaje. */
    public function toArray(): array
    {
        return [
            'formula_version' => (int) config('trayectos.physics.formula_version'),
            'inputs' => $this->inputs,
            'breakdown' => [
                'total_cost_cents' => $this->totalCents,
                'flat_cost_cents' => $this->flatCostCents,
                'hill_surcharge_cents' => $this->hillSurchargeCents(),
                'hill_surcharge_percent' => $this->hillSurchargePercent(),
                'litres' => round($this->litres, 3),
                'kwh' => round($this->kwh, 3),
                'mass_kg' => round($this->massKg, 1),
                'effective_rise_m' => round($this->effectiveRiseM, 1),
                'regen_ceiling_m' => round($this->regenCeilingM, 1),
                'usable_descent_m' => round($this->usableDescentM, 1),
                'ev_share' => round($this->evShare, 4),
            ],
        ];
    }
}
