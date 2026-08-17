<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cuenta contable del libro mayor: toda línea de asiento apunta a un miembro,
 * no a un usuario. Así una persona puede estar en varios grupos con saldos
 * independientes.
 */
#[Fillable(['group_id', 'user_id', 'role', 'active'])]
class GroupMember extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function drivenTrips(): HasMany
    {
        return $this->hasMany(Trip::class, 'driver_member_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /** Saldo en céntimos: (+) le deben · (−) debe. Siempre derivado, nunca guardado. */
    public function balanceCents(): int
    {
        return (int) $this->journalLines()->sum('amount_cents');
    }

    public function name(): string
    {
        return $this->user?->name ?? 'Miembro #'.$this->id;
    }
}
