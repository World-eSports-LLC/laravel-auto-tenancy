<?php

namespace Worldesports\MultiTenancy\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Model;
use Worldesports\MultiTenancy\Facades\MultiTenancy;
use Worldesports\MultiTenancy\Services\TenantResolverService;

class SetTenantOnLogin
{
    private TenantResolverService $tenantResolver;

    public function __construct(?TenantResolverService $tenantResolver = null)
    {
        $this->tenantResolver = $tenantResolver ?? app(TenantResolverService::class);
    }

    public function handle(Login $event): void
    {
        /** @var Model $user */
        $user = $event->user;

        if (! ($user instanceof Model)) {
            return;
        }

        $tenant = $this->tenantResolver->resolveForUser($user, request());

        if (! $tenant || ! MultiTenancy::userHasAccessToTenant($user, $tenant)) {
            return;
        }

        MultiTenancy::setTenant($tenant);

        // Log successful tenant detection for debugging
        if (config('multi-tenancy.security.log_tenant_switches', false)) {
            \Log::info("Tenant switched for user {$user->getKey()} to tenant {$tenant->id} ({$tenant->name})");
        }
    }
}
