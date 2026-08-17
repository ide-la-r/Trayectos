<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vista de sólo lectura sobre journal_lines. El saldo nunca se almacena en una
 * columna editable: se deriva siempre de los asientos.
 */
class MemberBalance extends Model
{
    protected $table = 'member_balances';

    protected $primaryKey = 'group_member_id';

    public $timestamps = false;

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'balance_cents' => 'integer',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(GroupMember::class, 'group_member_id');
    }

    public function euros(): float
    {
        return $this->balance_cents / 100;
    }

    public function save(array $options = []): bool
    {
        throw new \LogicException('member_balances es una vista derivada: no se escribe.');
    }
}
