<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class StartSessionMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Session is handled by Laravel's web middleware group.
        // This is a placeholder to match the old session_start() pattern.
        return $next($request);
    }
}
