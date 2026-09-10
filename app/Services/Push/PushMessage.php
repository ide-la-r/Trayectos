<?php

declare(strict_types=1);

namespace App\Services\Push;

/**
 * Lo que se ve en la pantalla de bloqueo.
 *
 * El título es lo único que se lee seguro, así que lleva lo que ha pasado; el
 * cuerpo, el detalle. La URL es a dónde va al tocarlo: un aviso que abre la
 * portada obliga a buscar de qué hablaba.
 */
final class PushMessage
{
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly string $url = '/panel',
        public readonly ?string $tag = null,
    ) {}

    public function toJson(): string
    {
        return (string) json_encode([
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            // Con la misma etiqueta, un aviso nuevo sustituye al anterior en
            // vez de apilarse. Cinco avisos del mismo viaje no ayudan a nadie.
            'tag' => $this->tag,
        ], JSON_UNESCAPED_UNICODE);
    }
}
