<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAdvisoryAccess
{
    /**
     * Handle an incoming request.
     * 
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $requiredTier  // Catches 'regular' or 'premium' from the route definition
     */
    public function handle(Request $request, Closure $next, string $requiredTier): Response
    {
        $user = $request->user();

        // 1. Ensure user is authenticated
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Authentication required.'
            ], 401);
        }

        // 2. Validate subscription base layer status
        if (!$user->hasActiveSubscription()) {
            return response()->json([
                'success' => false,
                'error' => 'An active subscription is required to view advisory data.',
                'code' => 'SUBSCRIPTION_REQUIRED'
            ], 403);
        }

        // 3. Resolve Tier Logic
        // If the route strictly demands premium tier, evaluate against user's dynamic tier attribute
        if ($requiredTier === 'premium' && $user->current_tier !== 'premium') {
            return response()->json([
                'success' => false,
                'error' => 'Premium Advisory subscription tier required to access this feature.',
                'code' => 'PREMIUM_TIER_REQUIRED'
            ], 403);
        }

        return $next($request);
    }
}