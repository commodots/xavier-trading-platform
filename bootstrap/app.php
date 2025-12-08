<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        // --- Web Middleware Stack ---
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);
		
		$middleware->alias([
			'admin' => \App\Http\Middleware\AdminMiddleware::class,
			'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
		]);

        // --- API Middleware Stack ---
        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class, // Sanctum for SPA/token auth
            SubstituteBindings::class,                // Enables route model binding
        ]);


    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
