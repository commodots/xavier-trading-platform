<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->google2fa_enabled) {
            throw ValidationException::withMessages([
                '2fa' => ['You must enable Two-Factor Authentication before withdrawing.'],
            ]);
        }

        return $next($request);
    }
}
