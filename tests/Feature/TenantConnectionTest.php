<?php

namespace Worldesports\MultiTenancy\Tests\Feature;

use Worldesports\MultiTenancy\Facades\MultiTenancy;
use Worldesports\MultiTenancy\Models\Tenant;
use Worldesports\MultiTenancy\Models\TenantDatabase;
use Worldesports\MultiTenancy\Tests\Concerns\UsesTestMigrations;
use Worldesports\MultiTenancy\Tests\TestCase;
use Worldesports\MultiTenancy\Tests\TestUser;

class TenantConnectionTest extends TestCase
{
    use UsesTestMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user
        $this->user = TestUser::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }

    /** @test */
    public function test_it_can_create_and_set_a_tenant()
    {
        // Create tenant
        $tenant = Tenant::create([
            'user_id' => $this->user->id,
            'name' => 'Test Tenant',
        ]);

        // Create tenant database
        $tenantDatabase = TenantDatabase::create([
            'tenant_id' => $tenant->id,
            'name' => 'test_db',
            'connection_details' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        ]);

        // Set the tenant
        MultiTenancy::setTenant($tenant);

        $this->assertTrue(MultiTenancy::hasTenant());
        $this->assertEquals($tenant->id, MultiTenancy::getTenantId());
        $this->assertEquals($tenant->name, MultiTenancy::getTenant()->name);
    }

    /** @test */
    public function test_it_can_reset_tenant_context()
    {
        // Create and set tenant
        $tenant = Tenant::create([
            'user_id' => $this->user->id,
            'name' => 'Test Tenant',
        ]);

        MultiTenancy::setTenant($tenant);
        $this->assertTrue(MultiTenancy::hasTenant());

        // Reset tenant
        MultiTenancy::resetTenant();
        $this->assertFalse(MultiTenancy::hasTenant());
        $this->assertNull(MultiTenancy::getTenant());
        $this->assertNull(MultiTenancy::getTenantId());
    }

    /** @test */
    public function test_it_can_switch_to_main_connection()
    {
        $originalConnection = config('database.default');

        // Create and set tenant
        $tenant = Tenant::create([
            'user_id' => $this->user->id,
            'name' => 'Test Tenant',
        ]);

        $tenantDatabase = TenantDatabase::create([
            'tenant_id' => $tenant->id,
            'name' => 'test_db',
            'connection_details' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        ]);

        MultiTenancy::setTenant($tenant);
        $this->assertNotEquals($originalConnection, config('database.default'));

        // Switch back to main
        MultiTenancy::switchToMainConnection();
        $this->assertEquals($originalConnection, config('database.default'));
    }
}
