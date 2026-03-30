<?php

namespace Worldesports\MultiTenancy\Tests\Feature;

use Worldesports\MultiTenancy\Models\Tenant;
use Worldesports\MultiTenancy\Models\TenantDatabase;
use Worldesports\MultiTenancy\Tests\Concerns\UsesTestMigrations;
use Worldesports\MultiTenancy\Tests\TestCase;
use Worldesports\MultiTenancy\Tests\TestUser;

class CommandTest extends TestCase
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
    public function test_tenant_status_command_shows_correct_information()
    {
        // Create test tenant
        $tenant = Tenant::create([
            'user_id' => $this->user->id,
            'name' => 'Test Tenant',
        ]);

        TenantDatabase::create([
            'tenant_id' => $tenant->id,
            'name' => 'test_db',
            'connection_details' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        ]);

        $this->artisan('tenant:status')
            ->expectsOutput('🏢 Multi-Tenancy Status')
            ->assertExitCode(0);
    }

    /** @test */
    public function test_tenant_status_command_with_list_option()
    {
        // Create test tenant
        $tenant = Tenant::create([
            'user_id' => $this->user->id,
            'name' => 'Test Tenant',
        ]);

        $this->artisan('tenant:status --list')
            ->expectsOutput('📋 Detailed Tenant List')
            ->assertExitCode(0);
    }

    /** @test */
    public function test_tenant_status_command_with_specific_tenant()
    {
        // Create test tenant
        $tenant = Tenant::create([
            'user_id' => $this->user->id,
            'name' => 'Test Tenant',
        ]);

        $this->artisan("tenant:status --tenant={$tenant->id}")
            ->expectsOutput("🏢 Tenant: {$tenant->name}")
            ->assertExitCode(0);
    }

    /** @test */
    public function test_install_command_publishes_files()
    {
        $this->artisan('tenant:install --force --skip-auth-check')
            ->expectsOutput('🚀 Installing Laravel Multi-Tenancy Package')
            ->expectsOutput('🎉 Multi-tenancy package installation completed!')
            ->assertExitCode(0);
    }
}
