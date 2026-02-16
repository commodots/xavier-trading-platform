<?php

namespace App\Providers;

use App\Models\KycProfile;
use App\Models\User;
use App\Observers\KycProfileObserver;
use App\Services\Stocks\Contracts\MarketDataProvider;
use App\Services\Stocks\Contracts\StockBroker;
use App\Services\Stocks\Mock\MockDriveWealthService;
use App\Services\Stocks\Mock\MockPolygonService;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register()
    {
        $this->app->singleton(\App\Services\BvnService::class);

        $this->app->bind(StockBroker::class, function () {
            return new MockDriveWealthService;
        });

        $this->app->bind(MarketDataProvider::class, function () {
            return new MockPolygonService;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        KycProfile::observe(KycProfileObserver::class);
    }
}
