<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        
        $staffRoles = ['admin', 'staff', 'compliance', 'manager', 'support', 'accounts'];

        if (!auth()->check()) {
            return response()->json([
                'message' => 'Access Denied: Staff or Admin privileges required.',
                'roles' => []
            ], 403);
        }

        $user = auth()->user();

        $hasLegacyRole = in_array($user->role, $staffRoles, true);
        $hasSpatieRole = false;
        try {
            $hasSpatieRole = method_exists($user, 'hasAnyRole') && $user->hasAnyRole($staffRoles);
        } catch (\Throwable $e) {
            $hasSpatieRole = false;
        }

        if (! $hasLegacyRole && ! $hasSpatieRole) {
            try {
                Log::warning('AdminMiddleware access denied', [
                    'user_id' => $user->id ?? null,
                    'legacy_role' => $user->role ?? null,
                    'spatie_roles' => method_exists($user, 'getRoleNames') ? $user->getRoleNames() : []
                ]);
            } catch (\Throwable $e) {
            }

            return response()->json([
                'message' => 'Access Denied: Staff or Admin privileges required.',
                'roles' => $user->getRoleNames() ?? []
            ], 403);
        }

        return $next($request);
    }
}
