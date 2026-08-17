<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'group_id', 'from_member_id', 'to_member_id', 'amount_cents',
    'method', 'settled_on', 'journal_entry_id', 'created_by',
])]
class Settlement extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'settled_on' => 'date',
            'amount_cents' => 'integer',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function from(): BelongsTo
    {
        return $this->belongsTo(GroupMember::class, 'from_member_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(GroupMember::class, 'to_member_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function amountEuros(): float
    {
        return $this->amount_cents / 100;
    }
}
