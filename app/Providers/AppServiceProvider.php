<?php

namespace App\Providers;

use App\Models\KycProfile;
use App\Models\Notification as CustomNotification;
use App\Models\User;
use App\Observers\KycProfileObserver;
use App\Observers\UserObserver;
use App\Services\CSL\CslClient;
use App\Services\CSL\CslMarketDataProvider;
use App\Services\CSL\CslStockBroker;
use App\Services\Stocks\Contracts\MarketDataProvider;
use App\Services\Stocks\Contracts\StockBroker;
use App\Services\Stocks\Mock\MockDriveWealthService;
use App\Services\Stocks\Mock\MockPolygonService;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Laravel\Pulse\Facades\Pulse;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register()
    {
        /*
    |--------------------------------------------------------------------------
    | Stock Broker
    |--------------------------------------------------------------------------
    */

        $this->app->bind(
            StockBroker::class,
            function ($app) {

                $driver = config(
                    'services.stock_broker',
                    env('STOCK_BROKER', 'mock')
                );

                if ($driver === 'csl') {
                    return $app->make(
                        CslStockBroker::class
                    );
                }

                return $app->make(
                    MockDriveWealthService::class
                );
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Market Data Provider
        |--------------------------------------------------------------------------
        */

        $this->app->bind(
            MarketDataProvider::class,
            function ($app) {

                $driver = config(
                    'services.market_data_provider',
                    env('MARKET_DATA_PROVIDER', 'mock')
                );

                if ($driver === 'csl') {
                    return $app->make(
                        CslMarketDataProvider::class
                    );
                }

                return $app->make(
                    MockPolygonService::class
                );
            }
        );

        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }

        $this->app->singleton(CslClient::class, function () {
            return new CslClient;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        KycProfile::observe(KycProfileObserver::class);

        User::observe(UserObserver::class);

        $this->app->bind(DatabaseNotification::class, function () {
            return new CustomNotification;
        });

        Gate::guessPolicyNamesUsing(function (string $modelClass) {
            return 'App\\Policies\\'.class_basename($modelClass).'Policy';
        });

        Gate::define('viewPulse', function (User $user) {
            return $user->isAdmin();
        });

        Pulse::user(fn ($user) => [
            'name' => $user->name,
            'extra' => $user->email,
            'avatar' => $user->avatar,
        ]);
    }
}
