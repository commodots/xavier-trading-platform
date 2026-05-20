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

    if (!$user) {
        return response()->json(['success' => false, 'error' => 'Unauthenticated.'], 401);
    }

    if ($user->subscription_status === 'suspended') {
        return response()->json([
            'success' => false, 
                'error' => 'Your subscription is suspended due to an outstanding balance or billing failure. Please top up your wallet.'
        ], 403);
    }

    if ($user->subscription_status === 'inactive') {
        return response()->json([
            'success' => false, 
            'error' => 'Your account profile is currently inactive. Please renew your subscription tier.'
        ], 403);
    }

    if (!$user->hasActiveSubscription()) {
        return response()->json([
            'success' => false, 
            'error' => 'An active subscription tier or trial is required to proceed.',
            'code' => 'SUBSCRIPTION_REQUIRED'
        ], 403);
    }

    $user->updateQuietly(['last_active_at' => now()]);

    return $next($request);
}
}