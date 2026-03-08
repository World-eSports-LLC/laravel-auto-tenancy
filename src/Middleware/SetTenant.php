<?php

namespace Worldesports\MultiTenancy\Middleware;

use Closure;
use Illuminate\Http\Request;
use Worldesports\MultiTenancy\Facades\MultiTenancy;
use Worldesports\MultiTenancy\Models\Tenant;

class SetTenant
{
    public function handle(Request $request, Closure $next, ?string $action = 'redirect')
    {
        if ($user = $request->user()) {
            /** @var \Illuminate\Database\Eloquent\Model $user */
            if (! ($user instanceof \Illuminate\Database\Eloquent\Model)) {
                return $this->handleError($request, $action, 'Invalid user model');
            }

            $tenant = Tenant::where('user_id', $user->getKey())->first();

            if ($tenant) {
                try {
                    MultiTenancy::setTenant($tenant);
                } catch (\Exception $e) {
                    return $this->handleError($request, $action, 'Failed to set tenant: '.$e->getMessage());
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
    }

    private function handleNoTenant(Request $request, string $action)
    {
        switch ($action) {
            case 'error':
                return response()->json(['error' => 'No tenant assigned to user'], 403);

            case 'redirect':
                $routeName = config('multi-tenancy.tenant_setup_route', 'tenant.setup');
                if ($routeName && \Illuminate\Support\Facades\Route::has($routeName)) {
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

                return response()->json(['error' => 'Internal server error'], 500);
        }
    }
}
