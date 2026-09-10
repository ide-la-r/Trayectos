<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un navegador que ha dicho que sí a los avisos. */
#[Fillable(['user_id', 'endpoint', 'endpoint_hash', 'public_key', 'auth_token'])]
class PushSubscription extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * La huella del endpoint, que es lo que lo identifica.
     *
     * El navegador puede renovar la suscripción y devolver el mismo endpoint:
     * hay que reconocerlo para actualizarla en vez de duplicarla.
     */
    public static function hash(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }
}
