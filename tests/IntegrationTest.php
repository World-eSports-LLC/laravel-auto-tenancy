<?php

namespace Worldesports\MultiTenancy\Tests;

use Illuminate\Auth\Events\Login;
use Worldesports\MultiTenancy\Tests\Concerns\UsesTestMigrations;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Worldesports\MultiTenancy\Facades\MultiTenancy;
use Worldesports\MultiTenancy\Models\Tenant;
use Worldesports\MultiTenancy\Models\TenantDatabase;
use Worldesports\MultiTenancy\Tests\TestUser;

class IntegrationTest extends TestCase
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
    public function test_it_can_create_tenant_with_database_connection()
    {
        // Create tenant
        $tenant = Tenant::create([
            'user_id' => $this->user->id,
            'name' => 'Test Company',
        ]);

        // Create tenant database
        $tenantDb = TenantDatabase::create([
            'tenant_id' => $tenant->id,
            'name' => 'test_tenant_db',
            'connection_details' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        ]);

        $this->assertTrue($tenant->exists);
        $this->assertTrue($tenantDb->exists);
        $this->assertCount(1, $tenant->databases);
    }

    /** @test */
    public function test_it_switches_database_connections_when_setting_tenant()
    {
        $originalConnection = config('database.default');

        // Create tenant with database
        $tenant = $this->createTenantWithDatabase();

        // Set tenant
        MultiTenancy::setTenant($tenant);

        // Check that connection switched
        $this->assertTrue(MultiTenancy::hasTenant());
        $this->assertSame($tenant->id, MultiTenancy::getTenant()->id);
        $this->assertNotSame($originalConnection, config('database.default'));
        $this->assertStringContainsString('tenant_connection_', MultiTenancy::getCurrentConnectionName());
    }

    /** @test */
    public function test_it_automatically_sets_tenant_on_login_event()
    {
        // Create tenant
        $tenant = $this->createTenantWithDatabase();

        $authenticatableUser = $this->user;

        // Ensure no tenant is set initially
        $this->assertFalse(MultiTenancy::hasTenant());

        // Fire login event
        Event::dispatch(new Login('web', $authenticatableUser, false));

        // Check that tenant was automatically set
        $this->assertTrue(MultiTenancy::hasTenant());
        $this->assertSame($tenant->id, MultiTenancy::getTenant()->id);
    }

    /** @test */
    public function test_it_can_switch_back_to_main_connection()
    {
        $originalConnection = config('database.default');

        // Create and set tenant
        $tenant = $this->createTenantWithDatabase();
        MultiTenancy::setTenant($tenant);

        // Verify we're on tenant connection
        $this->assertNotSame($originalConnection, config('database.default'));

        // Switch back to main
        MultiTenancy::switchToMainConnection();

        // Verify we're back on main connection
        $this->assertSame($originalConnection, config('database.default'));
        $this->assertNull(MultiTenancy::getCurrentConnectionName());
    }

    /** @test */
    public function test_it_can_reset_tenant_context_completely()
    {
        // Create and set tenant
        $tenant = $this->createTenantWithDatabase();
        MultiTenancy::setTenant($tenant);

        // Verify tenant is set
        $this->assertTrue(MultiTenancy::hasTenant());
        $this->assertNotNull(MultiTenancy::getTenant());

        // Reset tenant
        MultiTenancy::resetTenant();

        // Verify everything is reset
        $this->assertFalse(MultiTenancy::hasTenant());
        $this->assertNull(MultiTenancy::getTenant());
        $this->assertNull(MultiTenancy::getTenantId());
        $this->assertNull(MultiTenancy::getCurrentConnectionName());
    }

    /** @test */
    public function test_it_retrieves_tenant_database_metadata()
    {
        $tenant = $this->createTenantWithDatabase();

        // Add some metadata
        $tenant->databases->first()->metadata()->create([
            'key' => 'version',
            'value' => '1.0.0',
        ]);

        MultiTenancy::setTenant($tenant);

        $metadata = MultiTenancy::getTenantDatabaseMetadata();

        $this->assertCount(1, $metadata);
        $this->assertSame('test_tenant_db', $metadata[0]['name']);
        $this->assertSame('1.0.0', $metadata[0]['metadata']['version']);
        $this->assertSame('sqlite', $metadata[0]['connection_info']['driver']);
    }

    /** @test */
    public function test_it_handles_tenant_without_databases_gracefully()
    {
        // Create tenant without database
        $tenant = Tenant::create([
            'user_id' => $this->user->id,
            'name' => 'Test Company No DB',
        ]);

        MultiTenancy::setTenant($tenant);

        $this->assertTrue(MultiTenancy::hasTenant());
        $this->assertSame($tenant->id, MultiTenancy::getTenant()->id);
        $this->assertNull(MultiTenancy::getCurrentConnectionName());
    }

    /** @test */
    public function test_it_throws_exception_for_invalid_database_configuration()
    {
        $tenant = Tenant::create([
            'user_id' => $this->user->id,
            'name' => 'Test Company',
        ]);

        // Create tenant database with invalid config
        $tenantDb = TenantDatabase::create([
            'tenant_id' => $tenant->id,
            'name' => 'test_tenant_db',
            'connection_details' => [], // Missing required fields
        ]);

        $tenant->load('databases');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Database driver is required');

        MultiTenancy::setTenant($tenant);
    }

    /** @test */
    public function test_service_provider_registers_multitenancy_as_singleton()
    {
        $instance1 = app(\Worldesports\MultiTenancy\MultiTenancy::class);
        $instance2 = app(\Worldesports\MultiTenancy\MultiTenancy::class);

        $this->assertSame($instance1, $instance2);
    }

    /** @test */
    public function test_facade_works_correctly()
    {
        $this->assertFalse(MultiTenancy::hasTenant());

        $tenant = $this->createTenantWithDatabase();
        MultiTenancy::setTenant($tenant);

        $this->assertTrue(MultiTenancy::hasTenant());
        $this->assertSame($tenant->id, MultiTenancy::getTenantId());
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
