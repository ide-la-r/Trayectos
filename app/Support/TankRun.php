<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Un depósito recorrido: de un llenado completo al siguiente.
 *
 * Es la única medida de consumo que no depende de que se apunte nada más. El
 * depósito estaba lleno, se han recorrido unos kilómetros, y lo que ha cabido
 * al volver a llenarlo es exactamente lo que se ha gastado en ellos. No hace
 * falta el modelo físico, ni que los viajes estén apuntados, ni creerse la
 * ficha del fabricante.
 *
 * Los repostajes parciales de en medio cuentan: también entraron en el
 * depósito. Por eso los litros no son los del último repostaje sino los de
 * todos los que hubo desde el llenado anterior.
 */
final class TankRun
{
    public function __construct(
        /** El repostaje que cierra el depósito: es el que lleva la medida. */
        public readonly int $refuelId,
        public readonly CarbonInterface $from,
        public readonly CarbonInterface $to,
        public readonly int $km,
        public readonly ?float $litres,
        public readonly ?float $kwh,
    ) {}

    public function litresPer100(): ?float
    {
        return $this->per100($this->litres);
    }

    public function kwhPer100(): ?float
    {
        return $this->per100($this->kwh);
    }

    /**
     * Lo que se ha gastado por cada cien kilómetros, que es como se habla de
     * esto en todas partes.
     */
    private function per100(?float $amount): ?float
    {
        if ($amount === null || $amount <= 0 || $this->km <= 0) {
            return null;
        }

        return round($amount / $this->km * 100, 2);
    }
}
