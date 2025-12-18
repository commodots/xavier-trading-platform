<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!in_array(auth()->user()->role, ['admin', 'compliance'])) { 
        abort(403, 'Unauthorized'); 
    }

    return $next($request); 
    }
}
