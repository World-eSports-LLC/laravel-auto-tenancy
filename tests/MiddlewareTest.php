<?php

namespace Worldesports\MultiTenancy\Tests;

use Illuminate\Http\Request;
use Worldesports\MultiTenancy\Facades\MultiTenancy;
use Worldesports\MultiTenancy\Middleware\SetTenant;
use Worldesports\MultiTenancy\Models\Tenant;
use Worldesports\MultiTenancy\Models\TenantDatabase;
use Worldesports\MultiTenancy\Tests\Concerns\UsesTestMigrations;

class MiddlewareTest extends TestCase
{
    use UsesTestMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = TestUser::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }

    /** @test */
    public function test_middleware_sets_tenant_for_authenticated_user()
    {
        // Create tenant for user
        $tenant = $this->createTenantWithDatabase();

        // Create authenticated user
        $user = $this->user;

        // Create request with authenticated user
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        // Ensure no tenant is set initially
        $this->assertFalse(MultiTenancy::hasTenant());

        $tenantWasSetDuringRequest = false;

        // Process request through middleware
        $middleware = new SetTenant;
        $response = $middleware->handle($request, function ($req) use (&$tenantWasSetDuringRequest) {
            $tenantWasSetDuringRequest = MultiTenancy::hasTenant();

            return response('OK');
        });

        // Check that tenant was set during the request lifecycle
        $this->assertTrue($tenantWasSetDuringRequest);
        // Context is reset in finally, so it should now be cleared
        $this->assertFalse(MultiTenancy::hasTenant());
        $this->assertSame('OK', $response->getContent());
    }

    /** @test */
    public function test_middleware_does_nothing_for_unauthenticated_user()
    {
        // Create request without authenticated user
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(function () {
            return null;
        });

        // Ensure no tenant is set initially
        $this->assertFalse(MultiTenancy::hasTenant());

        // Process request through middleware
        $middleware = new SetTenant;
        $response = $middleware->handle($request, function ($req) {
            return response('OK');
        });

        // Check that no tenant was set
        $this->assertFalse(MultiTenancy::hasTenant());
        $this->assertSame('OK', $response->getContent());
    }

    /** @test */
    public function test_middleware_handles_user_without_tenant_gracefully()
    {
        $user = TestUser::factory()->create([
            'name' => 'Test User 2',
            'email' => 'test2@example.com',
        ]);

        // Create request with authenticated user
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        // Ensure no tenant is set initially
        $this->assertFalse(MultiTenancy::hasTenant());

        // Process request through middleware
        $middleware = new SetTenant;
        $response = $middleware->handle($request, function ($req) {
            return response('OK');
        });

        // Check that no tenant was set and request was redirected
        $this->assertFalse(MultiTenancy::hasTenant());
        $this->assertTrue($response->isRedirect());
        $this->assertSame('http://localhost', $response->getTargetUrl());
    }

    protected function createTenantWithDatabase(): Tenant
    {
        $tenant = Tenant::create([
            'user_id' => $this->user->id,
            'name' => 'Test Company',
        ]);

        TenantDatabase::create([
            'tenant_id' => $tenant->id,
            'name' => 'test_tenant_db',
            'connection_details' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        ]);

        return $tenant->fresh(['databases']);
    }
}
