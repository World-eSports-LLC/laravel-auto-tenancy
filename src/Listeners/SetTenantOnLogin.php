<?php

namespace Worldesports\MultiTenancy\Listeners;

use Illuminate\Auth\Events\Login;
use Worldesports\MultiTenancy\Facades\MultiTenancy;
use Worldesports\MultiTenancy\Models\Tenant;

class SetTenantOnLogin
{
    public function handle(Login $event): void
    {
        /** @var \Illuminate\Database\Eloquent\Model $user */
        $user = $event->user;

        // Strategy 1: Direct user_id mapping (current approach)
        $tenant = $this->findTenantByUserId($user);

        // Strategy 2: Email domain-based detection (automatic!)
        if (! $tenant && config('multi-tenancy.auto_detect_by_email', false)) {
            $tenant = $this->findTenantByEmailDomain($user);
        }

        // Strategy 3: Subdomain detection (automatic!)
        if (! $tenant && config('multi-tenancy.subdomain.enabled', false)) {
            $tenant = $this->findTenantBySubdomain();
        }

        // Strategy 4: Auto-create tenant for new users (automatic!)
        if (! $tenant && config('multi-tenancy.auto_create_tenant', false)) {
            $tenant = $this->createTenantForUser($user);
        }

        if ($tenant) {
            MultiTenancy::setTenant($tenant);

            // Log successful tenant detection for debugging
            if (config('multi-tenancy.security.log_tenant_switches', false)) {
                /** @var \Illuminate\Database\Eloquent\Model $user */
                $userId = $user instanceof \Illuminate\Database\Eloquent\Model ? $user->getKey() : 'unknown';
                \Log::info("Tenant switched for user {$userId} to tenant {$tenant->id} ({$tenant->name})");
            }
        }
    }

    /**
     * Strategy 1: Find tenant by direct user_id mapping
     */
    private function findTenantByUserId($user): ?Tenant
    {
        /** @var \Illuminate\Database\Eloquent\Model $user */
        if (! ($user instanceof \Illuminate\Database\Eloquent\Model)) {
            return null;
        }

        return Tenant::where('user_id', $user->getKey())->first();
    }

    /**
     * Strategy 2: Find tenant by email domain (company.com -> Company tenant)
     */
    private function findTenantByEmailDomain($user): ?Tenant
    {
        if (! isset($user->email)) {
            return null;
        }

        $domain = substr(strrchr($user->email, '@'), 1);

        // Skip generic email domains
        $genericDomains = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'icloud.com'];
        if (in_array(strtolower($domain), $genericDomains)) {
            return null;
        }

        // Look for tenant with matching domain
        $tenant = Tenant::where('domain', $domain)->first();

        if ($tenant) {
            // NOTE: Do not change tenant ownership/user_id based solely on email domain.
            // We only use the domain to *discover* the tenant, not to persist any ownership mapping.
            /** @var \Illuminate\Database\Eloquent\Model $user */
            $userId = $user instanceof \Illuminate\Database\Eloquent\Model ? $user->getKey() : 'unknown';
            \Log::info("Auto-detected tenant {$tenant->id} for user {$userId} via email domain: {$domain}");
        }

        return $tenant;
    }

    /**
     * Strategy 3: Find tenant by subdomain (tenant1.app.com)
     */
    private function findTenantBySubdomain(): ?Tenant
    {
        $host = request()->getHost();

        // Validate against allowed base domain to prevent Host header attacks
        $baseDomain = config('multi-tenancy.subdomain.base_domain');
        if ($baseDomain && ! str_ends_with($host, $baseDomain)) {
            \Log::warning("Invalid host header rejected for tenant detection: {$host}");

            return null;
        }

        $subdomain = explode('.', $host)[0];

        // Skip if no subdomain or excluded subdomain
        $excludedSubdomains = config('multi-tenancy.subdomain.excluded', ['www', 'app', 'api', 'admin']);
        if (in_array($subdomain, $excludedSubdomains) || $subdomain === $host) {
            return null;
        }

        return Tenant::where('subdomain', $subdomain)->first();
    }

    /**
     * Strategy 4: Auto-create tenant for new users
     */
    private function createTenantForUser($user): ?Tenant
    {
        try {
            $tenantName = $this->generateTenantName($user);

            $tenant = Tenant::create([
                'user_id' => $user->id,
                'name' => $tenantName,
                'domain' => $this->extractDomainFromUser($user),
                'subdomain' => $this->generateSubdomain($user),
            ]);

            // Create default database for the tenant if configured
            if (config('multi-tenancy.auto_create_database', false)) {
                $this->createDefaultDatabase($tenant, $user);
            }

            \Log::info("Auto-created tenant {$tenant->id} for user {$user->id}");

            return $tenant;

        } catch (\Exception $e) {
            \Log::error("Failed to auto-create tenant for user {$user->id}: {$e->getMessage()}");

            return null;
        }
    }

    private function generateTenantName($user): string
    {
        $template = config('multi-tenancy.default_tenant_name', 'Tenant for :name');

        return str_replace(':name', $user->name ?? 'User', $template);
    }

    private function extractDomainFromUser($user): ?string
    {
        if (! isset($user->email)) {
            return null;
        }

        $domain = substr(strrchr($user->email, '@'), 1);
        $genericDomains = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com'];

        return in_array($domain, $genericDomains) ? null : $domain;
    }

    private function generateSubdomain($user): string
    {
        // Generate a unique subdomain based on user info
        $base = strtolower(str_replace(' ', '', $user->name ?? 'user'));
        $base = preg_replace('/[^a-z0-9]/', '', $base);

        // Ensure uniqueness
        $counter = 1;
        $subdomain = $base;
        while (Tenant::where('subdomain', $subdomain)->exists()) {
            $subdomain = $base.$counter;
            $counter++;
        }

        return $subdomain;
    }

    private function createDefaultDatabase(Tenant $tenant, $user): void
    {
        // Only create if main database connection details are configured
        $defaultConnection = config('database.connections.'.config('database.default'));

        if (! $defaultConnection) {
            return;
        }

        // Create a database for this tenant using same connection but different DB name
        $dbName = 'tenant_'.$tenant->id.'_'.uniqid();

        \Worldesports\MultiTenancy\Models\TenantDatabase::create([
            'tenant_id' => $tenant->id,
            'name' => $dbName,
            'connection_details' => [
                'driver' => $defaultConnection['driver'],
                'host' => $defaultConnection['host'],
                'port' => $defaultConnection['port'],
                'database' => $dbName,
                'username' => $defaultConnection['username'],
                'password' => $defaultConnection['password'],
                'charset' => $defaultConnection['charset'] ?? 'utf8mb4',
                'collation' => $defaultConnection['collation'] ?? 'utf8mb4_unicode_ci',
            ],
        ]);
    }
}
