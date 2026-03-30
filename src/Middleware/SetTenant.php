<?php

namespace Worldesports\MultiTenancy\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Worldesports\MultiTenancy\Facades\MultiTenancy;
use Worldesports\MultiTenancy\Models\Tenant;

class SetTenant
{
    public function handle(Request $request, Closure $next, ?string $action = 'redirect')
    {
        try {
            if ($user = $request->user()) {
                /** @var Model $user */
                if (! ($user instanceof Model)) {
                    $response = $this->handleError($request, $action, 'Invalid user model');
                    if ($response) {
                        return $response;
                    }
                }

                $tenant = Tenant::where('user_id', $user->getKey())
                    ->orderByRaw('(SELECT COUNT(*) FROM tenant_databases WHERE tenant_id = tenants.id AND is_primary = true) DESC')
                    ->first();

                if ($tenant) {
                    try {
                        MultiTenancy::setTenant($tenant);
                    } catch (\Exception $e) {
                        $response = $this->handleError($request, $action, 'Failed to set tenant: '.$e->getMessage());
                        if ($response) {
                            return $response;
                        }
                    }
                } else {
                    // No tenant found for user
                    return $this->handleNoTenant($request, $action);
                }
            } else {
                // No authenticated user
                if ($action === 'error') {
                    return response()->json(['error' => 'Authentication required'], 401);
                }
            }

            return $next($request);
        } finally {
            // IMPORTANT: Reset tenant context after request completes to prevent leakage
            // in long-lived worker processes (Octane, queue workers, etc.).
            // Each HTTP request gets fresh context.
            MultiTenancy::resetTenant();
        }
    }

    private function handleNoTenant(Request $request, string $action)
    {
        switch ($action) {
            case 'error':
                return response()->json(['error' => 'No tenant assigned to user'], 403);

            case 'redirect':
                $routeName = config('multi-tenancy.tenant_setup_route', 'tenant.setup');
                if ($routeName && Route::has($routeName)) {
                    return redirect()->route($routeName)->with('error', 'Please contact support to set up your tenant.');
                }

                // Fallback to back or home if route doesn't exist
                return redirect()->back()->with('error', 'Please contact support to set up your tenant.');

            case 'ignore':
            default:
                return response()->json(['warning' => 'No tenant assigned'], 200);
        }
    }

    private function handleError(Request $request, string $action, string $message)
    {
        switch ($action) {
            case 'error':
                return response()->json(['error' => $message], 500);

            case 'redirect':
                return redirect()->back()->with('error', $message);

            case 'ignore':
            default:
                \Log::error("Tenant middleware error: $message");

                return null;
        }
    }
}
