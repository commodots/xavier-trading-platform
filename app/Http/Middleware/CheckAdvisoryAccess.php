<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAdvisoryAccess
{
    public function handle(Request $request, Closure $next, string $requiredTier = 'regular'): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
 
        // Admins always get through
        if ($user->hasRole('admin')) {
            return $next($request);
        }
 
        // Basic check: Does the user have any active subscription or trial?
        // The hasActiveSubscription() method on the User model already includes trials.
        if (!$user->hasActiveSubscription()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Please start a trial or subscribe.',
            ], 403);
        }
 
        // Tier check: Does the user's current tier meet the required tier for the route?
        if ($requiredTier === 'premium') {
            // The `current_tier` accessor on the User model handles all the logic.
            $userTier = $user->current_tier;
 
                        if ($userTier !== 'premium') {
                return response()->json([
                    'success' => false,
                    'message' => 'This feature requires a VIP subscription.',
                ], 403);
            }
        }
        return $next($request);
    }
}
