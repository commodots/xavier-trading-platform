<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class KycLevelMiddleware
{
    public function handle(Request $request, Closure $next, int $requiredLevel): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $currentLevel = (int) ($user->kyc?->tier ?? 0);

        if ($currentLevel < $requiredLevel) {
            return response()->json([
                'success' => false,
                'message' => "This action requires KYC Level {$requiredLevel} verification.",
                'required_level' => $requiredLevel,
                'current_level' => $currentLevel,
            ], 403);
        }

        return $next($request);
    }
}
