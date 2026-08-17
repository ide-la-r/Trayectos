<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Precio del kWh: PVPC de Red Eléctrica o tarifa declarada por el usuario. */
#[Fillable(['source', 'price_milli', 'observed_at'])]
class EnergyPrice extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price_milli' => 'integer',
            'observed_at' => 'datetime',
        ];
    }

    public function euros(): float
    {
        return $this->price_milli / 1000;
    }
}
