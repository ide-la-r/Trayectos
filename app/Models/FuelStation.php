<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Geo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'ideess', 'label', 'address', 'municipality', 'province', 'province_id',
    'postal_code', 'schedule', 'lat', 'lon',
])]
class FuelStation extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lon' => 'float',
        ];
    }

    public function prices(): HasMany
    {
        return $this->hasMany(FuelPrice::class);
    }

    /**
     * Filtro por caja envolvente antes de calcular distancias reales: sin
     * PostGIS, ordenar 11.500 estaciones por haversine en SQL es innecesario.
     */
    public function scopeNear(Builder $query, float $lat, float $lon, float $radiusKm = 25): Builder
    {
        $latDelta = $radiusKm / 111.0;
        $lonDelta = $radiusKm / (111.0 * max(cos(deg2rad($lat)), 0.01));

        return $query
            ->whereBetween('lat', [$lat - $latDelta, $lat + $latDelta])
            ->whereBetween('lon', [$lon - $lonDelta, $lon + $lonDelta]);
    }

    public function distanceKmTo(float $lat, float $lon): float
    {
        return Geo::haversineKm($this->lat, $this->lon, $lat, $lon);
    }
}
