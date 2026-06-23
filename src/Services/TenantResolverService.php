<?php

namespace Worldesports\MultiTenancy\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Worldesports\MultiTenancy\Models\Tenant;

class TenantResolverService
{
    public function resolveForUser(Model $user, ?Request $request = null): ?Tenant
    {
        $tenant = $this->findTenantByUserId($user);

        if (! $tenant && config('multi-tenancy.auto_detect_by_email', false)) {
            $tenant = $this->findTenantByEmailDomain($user);
        }

        if (! $tenant && $request && config('multi-tenancy.subdomain.enabled', false)) {
            $tenant = $this->findTenantBySubdomain($request);
        }

        return $tenant;
    }

    public function findTenantByUserId(Model $user): ?Tenant
    {
        return Tenant::where('user_id', $user->getKey())->first();
    }

    public function findTenantByEmailDomain(Model $user): ?Tenant
    {
        $email = (string) $user->getAttribute('email');

        if (! str_contains($email, '@')) {
            return null;
        }

        $domain = strtolower(Str::afterLast($email, '@'));

        $genericDomains = config('multi-tenancy.generic_email_domains', [
            'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'icloud.com',
        ]);

        if (in_array($domain, $genericDomains, true)) {
            return null;
        }

        return Tenant::where('domain', $domain)->first();
    }

    public function findTenantBySubdomain(Request $request): ?Tenant
    {
        $host = strtolower($request->getHost());
        $baseDomain = strtolower((string) config('multi-tenancy.subdomain.base_domain'));

        if ($baseDomain !== '' && $host !== $baseDomain && ! Str::endsWith($host, '.'.$baseDomain)) {
            return null;
        }

        if ($baseDomain !== '' && $host === $baseDomain) {
            return null;
        }

        $subdomain = $baseDomain !== ''
            ? Str::before($host, '.'.$baseDomain)
            : explode('.', $host)[0];

        $excluded = config('multi-tenancy.subdomain.excluded', ['www', 'app', 'api', 'admin']);

        if ($subdomain === '' || in_array($subdomain, $excluded, true)) {
            return null;
        }

        return Tenant::where('subdomain', $subdomain)->first();
    }
}
