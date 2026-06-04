<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class KycLevelMiddleware
{
    public function handle(Request $request, Closure $next, int $required = 1): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($user->verification_level < $required) {
            $messages = [
                1 => 'Please verify your email address to continue.',
                2 => 'BVN and NIN verification required. Complete identity verification to continue.',
                3 => 'Face verification required to access withdrawals.',
            ];

            return response()->json([
                'message'            => $messages[$required] ?? 'Higher verification level required.',
                'verification_level' => $user->verification_level,
                'required_level'     => $required,
                'action'             => 'complete_kyc',
            ], 403);
        }

        return $next($request);
    }
}
