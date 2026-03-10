<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAdvisoryAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        //Check if user is logged in
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Allow if user is an admin/staff
        if ($user->hasRole('admin')) {
            return $next($request);
        }

        // Check Trial or Subscription status
        if ($user->onTrial() || $user->hasActiveSubscription()) {
            return $next($request);
        }

        // If they fail both, block access
        return response()->json([
            'success' => false,
            'message' => 'Trial expired. Please subscribe to access advisory content.',
            'trial_expired' => true
        ], 403);
    }
}