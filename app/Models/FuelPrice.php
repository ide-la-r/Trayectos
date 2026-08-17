<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FuelKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['fuel_station_id', 'fuel_kind', 'price_milli', 'observed_at'])]
class FuelPrice extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'fuel_kind' => FuelKind::class,
            'price_milli' => 'integer',
            'observed_at' => 'datetime',
        ];
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(FuelStation::class, 'fuel_station_id');
    }

    public function euros(): float
    {
        return $this->price_milli / 1000;
    }
}
