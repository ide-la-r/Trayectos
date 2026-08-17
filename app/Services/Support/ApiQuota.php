<?php

declare(strict_types=1);

namespace App\Services\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Contador diario de llamadas para las APIs con cuota gratuita.
 *
 * Agotar la cuota de OpenRouteService no puede romper la creación de un viaje:
 * el planificador degrada a distancia en línea recta y avisa. Para eso hay que
 * saber, antes de llamar, si queda margen.
 */
final class ApiQuota
{
    public function __construct(
        private readonly string $service,
        private readonly int $dailyLimit,
    ) {}

    public function hasRoom(int $calls = 1): bool
    {
        return ($this->used() + $calls) <= $this->dailyLimit;
    }

    public function used(): int
    {
        return (int) Cache::get($this->key(), 0);
    }

    public function remaining(): int
    {
        return max(0, $this->dailyLimit - $this->used());
    }

    public function consume(int $calls = 1): void
    {
        $key = $this->key();

        // La ventana del proveedor es deslizante; la nuestra es el día natural,
        // que es más conservador y no necesita estado extra.
        Cache::add($key, 0, now()->endOfDay()->addMinutes(5));
        Cache::increment($key, $calls);
    }

    public function reset(): void
    {
        Cache::forget($this->key());
    }

    private function key(): string
    {
        return "api-quota:{$this->service}:".now()->toDateString();
    }
}
