<?php

namespace App\Providers;

use App\Models\KycProfile;
use App\Observers\KycProfileObserver;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Pulse\Facades\Pulse;
use App\Services\Stocks\Contracts\MarketDataProvider;
use App\Services\Stocks\Contracts\StockBroker;
use App\Services\Stocks\Mock\MockDriveWealthService;
use App\Services\Stocks\Mock\MockPolygonService;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use App\Models\Notification as CustomNotification;
use App\Services\CSL\CslClient;
use Illuminate\Notifications\DatabaseNotification;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register()
    {
        if ($this->app->environment('local', 'testing')) {
            $this->app->bind(StockBroker::class, function () {
                return new MockDriveWealthService;
            });

            $this->app->bind(MarketDataProvider::class, function () {
                return new MockPolygonService;
            });
        } else {
            // Production services should be configured in another provider or via Environment-specific bindings.
            $this->app->bind(StockBroker::class, function () {
                throw new \RuntimeException('StockBroker not configured for production');
            });

            $this->app->bind(MarketDataProvider::class, function () {
                throw new \RuntimeException('MarketDataProvider not configured for production');
            });
        }

        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }

        $this->app->singleton(CslClient::class, function () {
        return new CslClient();
    });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        KycProfile::observe(KycProfileObserver::class);

        \App\Models\User::observe(\App\Observers\UserObserver::class);

        $this->app->bind(DatabaseNotification::class, function () {
            return new CustomNotification();
        });

        Gate::guessPolicyNamesUsing(function (string $modelClass) {
            return 'App\\Policies\\' . class_basename($modelClass) . 'Policy';
        });

        Gate::define('viewPulse', function (User $user) {
            return $user->isAdmin();
        });

        Pulse::user(fn($user) => [
            'name' => $user->name,
            'extra' => $user->email,
            'avatar' => $user->avatar,
        ]);
    }
}
