<?php

declare(strict_types=1);

namespace App\Services\Energy;

use App\Models\EnergyPrice;
use App\Services\Fuel\ResolvedPrice;

/**
 * Precio del kWh para vehículos eléctricos y enchufables.
 *
 * Un aviso importante sobre esto: el precio del mercado mayorista que publica
 * Red Eléctrica NO es lo que paga nadie en su factura — faltan peajes, cargos e
 * impuestos, que más que duplican el importe. Por eso manda siempre la tarifa
 * que el usuario declara; el dato de REE es un respaldo aproximado.
 */
final class EnergyPriceResolver
{
    public function resolve(): ResolvedPrice
    {
        $manual = EnergyPrice::where('source', 'manual')
            ->where('observed_at', '>=', now()->subDays(180))
            ->latest('observed_at')
            ->first();

        if ($manual) {
            return new ResolvedPrice(
                priceMilli: $manual->price_milli,
                source: 'manual',
                observedAt: $manual->observed_at,
            );
        }

        $ree = EnergyPrice::where('source', 'ree')
            ->where('observed_at', '>=', now()->subDays(7))
            ->latest('observed_at')
            ->first();

        if ($ree) {
            return new ResolvedPrice(
                priceMilli: $ree->price_milli,
                source: 'ree',
                observedAt: $ree->observed_at,
            );
        }

        return new ResolvedPrice(
            priceMilli: (int) config('trayectos.fallback_prices.kwh_milli'),
            source: 'fallback',
        );
    }

    /** Fija la tarifa que el usuario paga de verdad. Es la opción recomendada. */
    public function setManualPrice(float $eurosPerKwh): EnergyPrice
    {
        return EnergyPrice::create([
            'source' => 'manual',
            'price_milli' => (int) round($eurosPerKwh * 1000),
            'observed_at' => now(),
        ]);
    }
}
