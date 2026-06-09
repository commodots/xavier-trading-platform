<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class KycLevelMiddleware
{
    /**
     * Handle an incoming request.
     * Verifies if the user has reached the mandatory KYC level for a route.
     */
    public function handle(Request $request, Closure $next, int $requiredLevel): Response
    {
        $user = $request->user();

        if (!$user || ($user->verification_level ?? 0) < $requiredLevel) {
            return response()->json([
                'success' => false,
                'message' => "Access denied. This feature requires KYC Level {$requiredLevel}.",
                'required_level' => $requiredLevel,
                'current_level' => $user->verification_level ?? 0
            ], 403);
        }

        return $next($request);
    }
}
