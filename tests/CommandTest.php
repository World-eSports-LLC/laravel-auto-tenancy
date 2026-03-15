<?php

namespace Worldesports\MultiTenancy\Tests;

use Worldesports\MultiTenancy\Tests\Concerns\UsesTestMigrations;
use Worldesports\MultiTenancy\Models\Tenant;
use Worldesports\MultiTenancy\Models\TenantDatabase;

class CommandTest extends TestCase
{
    use UsesTestMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        TestUser::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }

    /** @test */
    public function testCreateTenantCommandCreatesTenantWithDatabase()
    {
        $this->artisan('tenant:create', [
            'user_id' => 1,
            'name' => 'Test Company',
            '--db-name' => 'test_tenant_db',
            '--db-driver' => 'sqlite',
        ])
            ->expectsOutput('✅ Tenant \'Test Company\' created successfully!')
            ->assertExitCode(0);

        // Verify tenant was created
        $tenant = Tenant::where('user_id', 1)->first();
        $this->assertNotNull($tenant);
        $this->assertEquals('Test Company', $tenant->name);

        // Verify tenant database was created
        $tenantDb = $tenant->databases()->first();
        $this->assertNotNull($tenantDb);
        $this->assertEquals('test_tenant_db', $tenantDb->name);
        $this->assertEquals('sqlite', $tenantDb->connection_details['driver']);
        $this->assertFalse(array_key_exists('username', $tenantDb->connection_details));
    }

    /** @test */
    public function testCreateTenantCommandFailsForNonexistentUser()
    {
        $this->artisan('tenant:create', [
            'user_id' => 999, // Non-existent user
            'name' => 'Test Company',
            '--db-name' => 'test_tenant_db',
            '--db-username' => 'test_user',
            '--db-password' => 'test_pass',
        ])
            ->expectsOutput('User with ID 999 not found.')
            ->assertExitCode(1);

        // Verify no tenant was created
        $this->assertEquals(0, Tenant::count());
    }

    /** @test */
    public function testCreateTenantCommandFailsIfTenantAlreadyExists()
    {
        // Create existing tenant
        Tenant::create([
            'user_id' => 1,
            'name' => 'Existing Company',
        ]);

        $this->artisan('tenant:create', [
            'user_id' => 1,
            'name' => 'Test Company',
            '--db-name' => 'test_tenant_db',
            '--db-username' => 'test_user',
            '--db-password' => 'test_pass',
        ])
            ->expectsOutput('Tenant already exists for user ID 1.')
            ->assertExitCode(1);

        // Verify only one tenant exists
        $this->assertEquals(1, Tenant::count());
    }

    /** @test */
    public function testCreateTenantCommandValidatesRequiredDatabaseCredentials()
    {
        $this->artisan('tenant:create', [
            'user_id' => 1,
            'name' => 'Test Company',
            '--db-name' => 'test_tenant_db',
            // Missing username and password
        ])
            ->expectsOutput('Database username and password are required for mysql driver.')
            ->assertExitCode(1);

        // Verify no tenant was created
        $this->assertEquals(0, Tenant::count());
    }

    /** @test */
    public function testTenantStatusCommandShowsNoTenantsMessage()
    {
        $this->artisan('tenant:status')
            ->expectsOutput('🏢 Multi-Tenancy Status')
            ->expectsOutput('Total Tenants: 0')
            ->expectsOutput('No tenants found. Create one with: php artisan tenant:create')
            ->assertExitCode(0);
    }

    /** @test */
    public function testTenantStatusCommandShowsExistingTenants()
    {
        // Create tenant with database
        $tenant = Tenant::create([
            'user_id' => 1,
            'name' => 'Test Company',
        ]);

        TenantDatabase::create([
            'tenant_id' => $tenant->id,
            'name' => 'test_tenant_db',
            'connection_details' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
            ],
        ]);

        $this->artisan('tenant:status')
            ->expectsOutput('🏢 Multi-Tenancy Status')
            ->expectsOutput('Total Tenants: 1')
            ->expectsOutput('Total Databases: 1')
            ->assertExitCode(0);
    }

    /** @test */
    public function testTenantStatusCommandShowsTenantsWithoutDatabases()
    {
        // Create tenant without database
        $tenant = Tenant::create([
            'user_id' => 1,
            'name' => 'Test Company No DB',
        ]);

        $this->artisan('tenant:status')
            ->expectsOutput('🏢 Multi-Tenancy Status')
            ->expectsOutput('Total Tenants: 1')
            ->assertExitCode(0);
    }

}
