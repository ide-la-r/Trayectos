<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Energy\ReeClient;
use App\Services\Fuel\FuelDataPruner;
use App\Services\Fuel\FuelPriceSynchronizer;
use Illuminate\Console\Command;
use Throwable;

class SyncFuelPrices extends Command
{
    protected $signature = 'trayectos:sync-prices
                            {--provinces= : Ids de provincia separados por comas (vacío = las de la configuración)}
                            {--all : Descarga nacional completa (~11.500 estaciones)}
                            {--no-prune : No limpiar lo que sobra al terminar}
                            {--skip-energy : No consultar el precio eléctrico}';

    protected $description = 'Sincroniza precios de carburante del Ministerio y el precio eléctrico de REE';

    public function handle(FuelPriceSynchronizer $synchronizer, ReeClient $ree, FuelDataPruner $pruner): int
    {
        $provinces = match (true) {
            (bool) $this->option('all') => [],
            filled($this->option('provinces')) => array_map('trim', explode(',', (string) $this->option('provinces'))),
            default => null,
        };

        try {
            $result = $synchronizer->sync($provinces);

            $this->info(sprintf(
                'Carburante: %d estaciones y %d precios (dato del Ministerio de %s).',
                $result['stations'],
                $result['prices'],
                $result['observed_at']->format('d/m/Y H:i'),
            ));
        } catch (Throwable $exception) {
            $this->error('No se han podido sincronizar los carburantes: '.$exception->getMessage());

            return self::FAILURE;
        }

        /*
         * La limpieza se salta en las ejecuciones a medida. Usa siempre la
         * configuración, así que un «--provinces=41» de una vez borraría todo
         * lo demás y acto seguido se borraría a sí mismo.
         */
        if ($provinces === null && ! $this->option('no-prune')) {
            $pruned = $pruner->prune();

            if ($pruned['stations'] || $pruned['prices']) {
                $this->info(sprintf(
                    'Limpieza: %d estaciones fuera de las provincias configuradas y %d precios de más de %d días.',
                    $pruned['stations'],
                    $pruned['prices'],
                    FuelDataPruner::KEEP_DAYS,
                ));
            }
        }

        if (! $this->option('skip-energy')) {
            $price = $ree->syncDailyPrice();

            $price
                ? $this->info('Electricidad: '.number_format($price->euros(), 3, ',', '.').' €/kWh estimados.')
                : $this->warn('Electricidad: REE no ha devuelto dato; se mantiene el anterior.');
        }

        return self::SUCCESS;
    }
}
