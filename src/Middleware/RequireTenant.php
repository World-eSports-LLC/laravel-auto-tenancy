<?php

namespace Worldesports\MultiTenancy\Middleware;

use Closure;
use Illuminate\Http\Request;
use Worldesports\MultiTenancy\Facades\MultiTenancy;

class RequireTenant
{
    public function handle(Request $request, Closure $next)
    {
        if (! MultiTenancy::hasTenant()) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Tenant context required'], 403);
            }

            abort(403, 'Tenant context required');
        }

        return $next($request);
    }
}
