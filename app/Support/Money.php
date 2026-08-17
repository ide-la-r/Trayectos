<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Todo el dinero de la aplicación vive en enteros de céntimos. Esta clase es
 * el único sitio donde se pasa de euros a céntimos y al revés.
 */
final class Money
{
    public static function fromEuros(float|string $euros): int
    {
        if (is_string($euros)) {
            $euros = (float) str_replace(',', '.', $euros);
        }

        return (int) round($euros * 100);
    }

    public static function toEuros(int $cents): float
    {
        return $cents / 100;
    }

    /**
     * Aquí se escribe «12,50», no «12.50». La validación 'numeric' rechaza la
     * coma, así que los importes se normalizan antes de validarlos.
     */
    public static function normalizeInput(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return str_replace(',', '.', trim($value));
    }

    public static function format(int $cents, bool $withSign = false): string
    {
        $sign = $withSign && $cents > 0 ? '+' : ($cents < 0 ? '−' : '');

        return $sign.number_format(abs($cents) / 100, 2, ',', '.').' €';
    }

    /** Milésimas de euro (precios de carburante) a céntimos por unidad. */
    public static function milliToEuros(int $milli): float
    {
        return $milli / 1000;
    }

    public static function formatPrice(int $milli, string $unit = 'L'): string
    {
        return number_format($milli / 1000, 3, ',', '.').' €/'.$unit;
    }
}
