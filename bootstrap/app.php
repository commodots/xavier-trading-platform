<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\CheckAdvisoryAccess;
use App\Http\Middleware\CheckSubscription;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsureTwoFactorEnabled;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\HandleUserLifecycle;
use App\Http\Middleware\KycLevelMiddleware;
use App\Http\Middleware\RateLimitSensitiveOperations;
use App\Jobs\UpdatePortfolioPerformance;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        // --- Web Middleware Stack ---
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // --- Middleware Aliases ---
        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'verified' => EnsureEmailIsVerified::class,
            'subscribed' => CheckSubscription::class,
            'advisory.access' => CheckAdvisoryAccess::class,
            'rate-limit-sensitive' => RateLimitSensitiveOperations::class,
            'kyc' => KycLevelMiddleware::class,
            '2fa' => EnsureTwoFactorEnabled::class,
        ]);

        // --- API Middleware Stack ---
        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
            SubstituteBindings::class,
        ]);

        $middleware->api(append: [
            HandleUserLifecycle::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('settlements:process')
            ->dailyAt('02:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/settlements.log'));

        $schedule->job(new UpdatePortfolioPerformance)
            ->dailyAt('00:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/portfolio_performance.log'));

        $schedule->command('telescope:prune --hours=48')->daily();

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
