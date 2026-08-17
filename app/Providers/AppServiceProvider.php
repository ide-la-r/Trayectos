<?php

namespace App\Providers;

use App\Services\Elevation\OpenTopoDataClient;
use App\Services\Routing\OpenRouteServiceClient;
use App\Services\Support\ApiQuota;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Cada API gratuita lleva su propio contador diario
        $this->app->when(OpenRouteServiceClient::class)
            ->needs(ApiQuota::class)
            ->give(fn () => new ApiQuota('ors', (int) config('trayectos.ors.daily_quota')));

        $this->app->when(OpenTopoDataClient::class)
            ->needs(ApiQuota::class)
            ->give(fn () => new ApiQuota('opentopodata', (int) config('trayectos.opentopodata.daily_quota')));
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        Date::use(Carbon::class);
        setlocale(LC_TIME, 'es_ES.UTF-8', 'Spanish_Spain');
    }
}
