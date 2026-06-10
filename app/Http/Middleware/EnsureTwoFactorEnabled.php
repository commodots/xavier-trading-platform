<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->google2fa_enabled) {
            return response()->json([
                'message' => '2FA required',
            ], 403);
        }

        return $next($request);
    }
}
