<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleUserLifecycle
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            // Update last active tracking
            $user->update(['last_active_at' => now()]);

            // Reactivation Flow
            if ($user->subscription_status === 'inactive') {
                $user->subscription_status = 'active';
                // Give them a 3-day grace period to fund their wallet upon returning
                $user->next_fee_due_at = now()->addDays(3);
                $user->save();

                // Optional: Trigger In-App notification "Welcome Back!" here
            }

            // Strict Gatekeeping
            if ($user->subscription_status === 'suspended') {
                return response()->json([
                    'error' => 'Account suspended due to outstanding debt. Please fund your wallet.',
                    'debt' => $user->wallet_debt
                ], 403);
            }
        }

        return $next($request);
    }
}
