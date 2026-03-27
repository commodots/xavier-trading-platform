<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RateLimitSensitiveOperations
{
    public function __construct(private RateLimiter $limiter) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Enforce strict rate-limits on sensitive trading/payment operations
        if ($this->isHighRiskOperation($request)) {
            $key = "sensitive:{$user->id}:".$request->path();

            // Allow 5 requests per 60 seconds for trading/withdrawals (prevent rapid succession attacks)
            if ($this->limiter->tooManyAttempts($key, 5)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many requests. Please wait before trying again.',
                ], 429);
            }

            $this->limiter->hit($key, 60);
        }

        return $next($request);
    }

    private function isHighRiskOperation(Request $request): bool
    {
        $highRiskPaths = [
            '/api/orders/place',         // Order placement
            '/api/orders/cancel',        // Order cancellation
            '/api/wallet/withdraw',      // Withdrawals
            '/api/wallet/deposit',       // Deposits
            '/api/subscriptions/initialize', // Payment initialization
            '/api/accounts/link',        // Account linking
            '/api/documents/upload',     // Document uploads (KYC)
            '/api/admin/users/toggle',   // Admin user toggle
            '/api/admin/settings/update', // System settings
        ];

        foreach ($highRiskPaths as $path) {
            if ($request->is($path)) {
                return true;
            }
        }

        return false;
    }
}
