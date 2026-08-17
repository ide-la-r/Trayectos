<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EntryKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Asiento del libro mayor. Sus líneas suman exactamente cero; corregir un
 * error nunca es editar, sino crear un asiento de anulación que lo revierte.
 */
#[Fillable(['group_id', 'kind', 'description', 'occurred_on', 'external_ref', 'reverses_id', 'created_by'])]
class JournalEntry extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'kind' => EntryKind::class,
            'occurred_on' => 'date',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_id');
    }

    public function reversal(): HasMany
    {
        return $this->hasMany(self::class, 'reverses_id');
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class, 'id', 'journal_entry_id');
    }

    public function isReversed(): bool
    {
        return $this->reversal()->exists();
    }

    /** Debe ser 0 siempre. Si no lo es, algo ha escrito saltándose el servicio. */
    public function balanceCents(): int
    {
        return (int) $this->lines()->sum('amount_cents');
    }

    /** Importe del asiento: la suma de las líneas positivas. */
    public function amountCents(): int
    {
        return (int) $this->lines()->where('amount_cents', '>', 0)->sum('amount_cents');
    }
}
