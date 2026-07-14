<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Illuminate\Support\Facades\Log;
use App\Reporting\Jobs\GenerateDailyPortfolioSnapshot;
use App\Reporting\Jobs\GenerateRevenueSnapshot;
use App\Reporting\Jobs\GenerateAnalyticsSnapshot;
use App\Reporting\Jobs\DeleteExpiredExports;
use App\Reporting\Jobs\WarmReportCache;
use App\Reporting\Jobs\PurgeOldCache;

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
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // --- Middleware Aliases ---
        $middleware->alias([
            'admin'                => \App\Http\Middleware\AdminMiddleware::class,
            'verified'             => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'subscribed'           => \App\Http\Middleware\CheckSubscription::class,
            'advisory.access'      => \App\Http\Middleware\CheckAdvisoryAccess::class,
            'rate-limit-sensitive' => \App\Http\Middleware\RateLimitSensitiveOperations::class,
            'kyc'                  => \App\Http\Middleware\KycLevelMiddleware::class,
            '2fa'                   => \App\Http\Middleware\EnsureTwoFactorEnabled::class,
        ]);

        // --- API Middleware Stack ---
        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
            SubstituteBindings::class,
        ]);

        $middleware->api(append: [
            \App\Http\Middleware\HandleUserLifecycle::class,
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

        $schedule->job(new \App\Jobs\UpdatePortfolioPerformance)
            ->dailyAt('00:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/portfolio_performance.log'));

        // Reporting Scheduled Jobs
        // Generate daily portfolio snapshots at 23:55
        $schedule->job(new GenerateDailyPortfolioSnapshot)
            ->dailyAt('23:55')
            ->withoutOverlapping()
            ->onFailure(function () {
                Log::error('Failed to execute GenerateDailyPortfolioSnapshot job');
            })
            ->appendOutputTo(storage_path('logs/reporting/portfolio-snapshot.log'));

        // Generate daily revenue snapshot at 23:58
        $schedule->job(new GenerateRevenueSnapshot)
            ->dailyAt('23:58')
            ->withoutOverlapping()
            ->onFailure(function () {
                Log::error('Failed to execute GenerateRevenueSnapshot job');
            })
            ->appendOutputTo(storage_path('logs/reporting/revenue-snapshot.log'));

        // Generate analytics snapshot every hour
        $schedule->job(new GenerateAnalyticsSnapshot)
            ->hourly()
            ->withoutOverlapping()
            ->onFailure(function () {
                Log::error('Failed to execute GenerateAnalyticsSnapshot job');
            })
            ->appendOutputTo(storage_path('logs/reporting/analytics-snapshot.log'));

        // Delete expired exports daily
        $schedule->job(new DeleteExpiredExports)
            ->daily()
            ->withoutOverlapping()
            ->onFailure(function () {
                Log::error('Failed to execute DeleteExpiredExports job');
            })
            ->appendOutputTo(storage_path('logs/reporting/delete-expired-exports.log'));

        // Warm report cache every 30 minutes
        $schedule->job(new WarmReportCache)
            ->everyThirtyMinutes()
            ->withoutOverlapping()
            ->onFailure(function () {
                Log::error('Failed to execute WarmReportCache job');
            })
            ->appendOutputTo(storage_path('logs/reporting/warm-cache.log'));

        // Purge old cache daily at midnight
        $schedule->job(new PurgeOldCache)
            ->daily()
            ->withoutOverlapping()
            ->onFailure(function () {
                Log::error('Failed to execute PurgeOldCache job');
            })
            ->appendOutputTo(storage_path('logs/reporting/purge-cache.log'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();