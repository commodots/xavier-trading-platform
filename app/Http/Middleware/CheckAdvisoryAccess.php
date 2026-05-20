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

    // Users must be authenticated to access advisory features
    if (!$user) {
        return response()->json(['success' => false, 'error' => 'Access Denied.'], 403);
    }

    // Strictly enforce premium access limits for paid insights
    if ($requiredTier === 'premium' && (!$user->hasActiveSubscription() || $user->getCurrentTierAttribute() !== 'premium')) {
        return response()->json([
            'success' => false,
            'error' => 'Premium Advisory subscription tier required to access this feature.',
            'code' => 'PREMIUM_TIER_REQUIRED'
        ], 403);
    }


    return $next($request);
}
}