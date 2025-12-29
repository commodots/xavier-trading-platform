<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check() || !in_array(auth()->user()->role, ['admin', 'compliance'])) {
            return response()->json([
                'message' => 'Access Denied: Admin privileges required.',
                'role' => auth()->user()->role ?? 'guest'
            ], 403);
        }

        return $next($request);
    }
}
