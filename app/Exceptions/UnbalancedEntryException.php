<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class UnbalancedEntryException extends RuntimeException
{
    public static function forTotal(int $totalCents): self
    {
        return new self(
            "El asiento no cuadra: las líneas suman {$totalCents} céntimos y deben sumar 0."
        );
    }
}
