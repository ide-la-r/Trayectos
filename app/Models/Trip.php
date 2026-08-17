<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'group_id', 'vehicle_id', 'driver_member_id', 'travelled_on',
    'origin_label', 'destination_label', 'origin_lat', 'origin_lon',
    'destination_lat', 'destination_lon', 'round_trip',
    'distance_m', 'ascent_m', 'descent_m', 'luggage_kg', 'battery_start_pct',
    'route_source', 'route_geometry', 'total_cost_cents', 'cost_inputs',
    'formula_version', 'journal_entry_id', 'notes', 'created_by',
])]
class Trip extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'travelled_on' => 'date',
            'round_trip' => 'boolean',
            'route_geometry' => 'array',
            'cost_inputs' => 'array',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(GroupMember::class, 'driver_member_id');
    }

    public function passengers(): HasMany
    {
        return $this->hasMany(TripPassenger::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function isPosted(): bool
    {
        return $this->journal_entry_id !== null;
    }

    public function distanceKm(): float
    {
        return $this->distance_m / 1000;
    }

    public function costEuros(): float
    {
        return $this->total_cost_cents / 100;
    }

    public function costPerKm(): float
    {
        return $this->distance_m > 0 ? $this->total_cost_cents / 100 / $this->distanceKm() : 0.0;
    }

    /** Cuánto del coste se debe al desnivel, según el snapshot del cálculo. */
    public function hillSurchargePercent(): float
    {
        $base = (float) data_get($this->cost_inputs, 'breakdown.flat_cost_cents', 0);

        if ($base <= 0) {
            return 0.0;
        }

        return round((($this->total_cost_cents - $base) / $base) * 100, 1);
    }
}
