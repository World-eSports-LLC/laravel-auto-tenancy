<?php

namespace Worldesports\MultiTenancy\Tests\Unit;

use Illuminate\Http\Request;
use Worldesports\MultiTenancy\Models\Tenant;
use Worldesports\MultiTenancy\Services\TenantResolverService;
use Worldesports\MultiTenancy\Tests\Concerns\UsesTestMigrations;
use Worldesports\MultiTenancy\Tests\TestCase;
use Worldesports\MultiTenancy\Tests\TestUser;

class TenantResolverServiceTest extends TestCase
{
    use UsesTestMigrations;

    public function test_resolves_existing_tenant_by_user_id(): void
    {
        $user = TestUser::factory()->create(['email' => 'owner@example.com']);
        $tenant = Tenant::create(['user_id' => $user->id, 'name' => 'Owner Tenant']);

        $resolved = app(TenantResolverService::class)->resolveForUser($user);

        $this->assertTrue($tenant->is($resolved));
    }

    public function test_resolves_email_domain_when_enabled(): void
    {
        config()->set('multi-tenancy.auto_detect_by_email', true);

        $owner = TestUser::factory()->create(['email' => 'owner@acme.test']);
        $user = TestUser::factory()->create(['email' => 'member@acme.test']);
        $tenant = Tenant::create([
            'user_id' => $owner->id,
            'name' => 'Acme',
            'domain' => 'acme.test',
        ]);

        $resolved = app(TenantResolverService::class)->resolveForUser($user);

        $this->assertTrue($tenant->is($resolved));
    }

    public function test_does_not_resolve_email_domain_by_default(): void
    {
        $owner = TestUser::factory()->create(['email' => 'owner@acme.test']);
        $user = TestUser::factory()->create(['email' => 'member@acme.test']);
        Tenant::create([
            'user_id' => $owner->id,
            'name' => 'Acme',
            'domain' => 'acme.test',
        ]);

        $resolved = app(TenantResolverService::class)->resolveForUser($user);

        $this->assertNull($resolved);
    }

    public function test_resolves_subdomain_only_for_valid_base_domain(): void
    {
        config()->set('multi-tenancy.subdomain.enabled', true);
        config()->set('multi-tenancy.subdomain.base_domain', 'example.com');

        $owner = TestUser::factory()->create(['email' => 'owner@example.com']);
        $tenant = Tenant::create([
            'user_id' => $owner->id,
            'name' => 'Acme',
            'subdomain' => 'acme',
        ]);

        $validRequest = Request::create('https://acme.example.com/dashboard');
        $spoofedRequest = Request::create('https://badexample.com/dashboard');

        $resolver = app(TenantResolverService::class);

        $this->assertTrue($tenant->is($resolver->findTenantBySubdomain($validRequest)));
        $this->assertNull($resolver->findTenantBySubdomain($spoofedRequest));
    }
}
