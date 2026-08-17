<?php

declare(strict_types=1);

namespace App\Enums;

enum EntryKind: string
{
    case Trip = 'trip';
    case Settlement = 'settlement';
    case Adjustment = 'adjustment';
    case Reversal = 'reversal';

    public function label(): string
    {
        return match ($this) {
            self::Trip => 'Trayecto',
            self::Settlement => 'Liquidación',
            self::Adjustment => 'Ajuste manual',
            self::Reversal => 'Anulación',
        };
    }
}
