<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['trip_id', 'group_member_id', 'weight'])]
class TripPassenger extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'weight' => 'float',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(GroupMember::class, 'group_member_id');
    }
}
