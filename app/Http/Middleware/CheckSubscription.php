<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Ensure the user is authenticated
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthenticated.'
            ], 401);
        }

        //Check for structural suspension (e.g., unpaid debt limits)
        if ($user->subscription_status === 'suspended') {
            return response()->json([
                'success' => false,
                'error' => 'Your subscription is suspended due to an outstanding balance or billing failure. Please top up your wallet.'
            ], 403);
        }

        // Check for administrative inactivation (e.g., extended long-term inactivity)
        if ($user->subscription_status === 'inactive') {
            return response()->json([
                'success' => false,
                'error' => 'Your account profile is currently inactive. Please renew your subscription tier to proceed.'
            ], 403);
        }

        //Ensure they have a valid, unexpired timeframe window
        if (!$user->hasActiveSubscription()) {
            return response()->json([
                'success' => false,
                'error' => 'An active premium subscription tier or trial is required to access this feature.'
            ], 403);
        }

        // Update user activity timestamp dynamically during valid incoming traffic requests
        $user->updateQuietly(['last_active_at' => now()]);

        return $next($request);
    }
}