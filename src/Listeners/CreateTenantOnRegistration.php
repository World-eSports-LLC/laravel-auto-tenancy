<?php

namespace Worldesports\MultiTenancy\Listeners;

use Illuminate\Auth\Events\Registered;
use Illuminate\Database\Eloquent\Model;
use Worldesports\MultiTenancy\Models\Tenant;

class CreateTenantOnRegistration
{
    public function handle(Registered $event): void
    {
        // Check if auto-tenant creation is enabled
        if (! config('multi-tenancy.auto_create_tenant', false)) {
            return;
        }

        $user = $event->user;

        /** @var Model $user */
        if (! ($user instanceof Model)) {
            return;
        }

        // Check if tenant already exists
        if (Tenant::where('user_id', $user->getKey())->exists()) {
            return;
        }

        try {
            // Create tenant with default name
            $userName = $user->getAttribute('name') ?? 'User';
            $tenantNameTemplate = config('multi-tenancy.default_tenant_name', 'Tenant for :name');
            $tenantName = str_replace(':name', $userName, $tenantNameTemplate);

            Tenant::create([
                'user_id' => $user->getKey(),
                'name' => $tenantName,
            ]);

            \Log::info("Auto-created tenant for user: {$user->getKey()}");

        } catch (\Exception $e) {
            \Log::error("Failed to auto-create tenant for user {$user->getKey()}: {$e->getMessage()}");
        }
    }
}
