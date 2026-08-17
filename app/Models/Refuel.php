<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Repostaje real. Alimenta la calibración del modelo de consumo. */
#[Fillable(['vehicle_id', 'refuelled_on', 'litres', 'kwh', 'cost_cents', 'odometer_km', 'full_tank'])]
class Refuel extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'refuelled_on' => 'date',
            'litres' => 'float',
            'kwh' => 'float',
            'full_tank' => 'boolean',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
