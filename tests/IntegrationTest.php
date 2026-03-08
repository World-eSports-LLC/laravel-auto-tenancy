<?php

namespace Worldesports\MultiTenancy\Tests;

use Illuminate\Auth\Events\Login;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Worldesports\MultiTenancy\Facades\MultiTenancy;
use Worldesports\MultiTenancy\Models\Tenant;
use Worldesports\MultiTenancy\Models\TenantDatabase;

class IntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user table FIRST (before package migrations with FK constraints)
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });

        // Create the tenant tables
        $this->runPackageMigrations();

        // Create test user
        DB::table('users')->insert([
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @test */
    public function it_can_create_tenant_with_database_connection()
    {
        // Create tenant
        $tenant = Tenant::create([
            'user_id' => 1,
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

        expect($tenant->exists)->toBeTrue();
        expect($tenantDb->exists)->toBeTrue();
        expect($tenant->databases)->toHaveCount(1);
    }

    /** @test */
    public function it_switches_database_connections_when_setting_tenant()
    {
        $originalConnection = config('database.default');

        // Create tenant with database
        $tenant = $this->createTenantWithDatabase();

        // Set tenant
        MultiTenancy::setTenant($tenant);

        // Check that connection switched
        expect(MultiTenancy::hasTenant())->toBeTrue();
        expect(MultiTenancy::getTenant()->id)->toBe($tenant->id);
        expect(config('database.default'))->not->toBe($originalConnection);
        expect(MultiTenancy::getCurrentConnectionName())->toContain('tenant_connection_');
    }

    /** @test */
    public function it_automatically_sets_tenant_on_login_event()
    {
        // Create tenant
        $tenant = $this->createTenantWithDatabase();

        // Get the actual user from the database
        $user = DB::table('users')->where('id', 1)->first();

        // Create a proper Authenticatable user instance
        $authenticatableUser = new class($user) implements \Illuminate\Contracts\Auth\Authenticatable
        {
            private $user;

            public function __construct($user)
            {
                $this->user = $user;
            }

            public function getAuthIdentifierName()
            {
                return 'id';
            }

            public function getAuthIdentifier()
            {
                return $this->user->id;
            }

            public function getAuthPassword()
            {
                return '';
            }

            public function getRememberToken()
            {
                return null;
            }

            public function setRememberToken($value) {}

            public function getRememberTokenName()
            {
                return null;
            }

            public function getAuthPasswordName()
            {
                return 'password';
            }
        };

        // Ensure no tenant is set initially
        expect(MultiTenancy::hasTenant())->toBeFalse();

        // Fire login event
        Event::dispatch(new Login('web', $authenticatableUser, false));

        // Check that tenant was automatically set
        expect(MultiTenancy::hasTenant())->toBeTrue();
        expect(MultiTenancy::getTenant()->id)->toBe($tenant->id);
    }

    /** @test */
    public function it_can_switch_back_to_main_connection()
    {
        $originalConnection = config('database.default');

        // Create and set tenant
        $tenant = $this->createTenantWithDatabase();
        MultiTenancy::setTenant($tenant);

        // Verify we're on tenant connection
        expect(config('database.default'))->not->toBe($originalConnection);

        // Switch back to main
        MultiTenancy::switchToMainConnection();

        // Verify we're back on main connection
        expect(config('database.default'))->toBe($originalConnection);
        expect(MultiTenancy::getCurrentConnectionName())->toBeNull();
    }

    /** @test */
    public function it_can_reset_tenant_context_completely()
    {
        // Create and set tenant
        $tenant = $this->createTenantWithDatabase();
        MultiTenancy::setTenant($tenant);

        // Verify tenant is set
        expect(MultiTenancy::hasTenant())->toBeTrue();
        expect(MultiTenancy::getTenant())->not->toBeNull();

        // Reset tenant
        MultiTenancy::resetTenant();

        // Verify everything is reset
        expect(MultiTenancy::hasTenant())->toBeFalse();
        expect(MultiTenancy::getTenant())->toBeNull();
        expect(MultiTenancy::getTenantId())->toBeNull();
        expect(MultiTenancy::getCurrentConnectionName())->toBeNull();
    }

    /** @test */
    public function it_retrieves_tenant_database_metadata()
    {
        $tenant = $this->createTenantWithDatabase();

        // Add some metadata
        $tenant->databases->first()->metadata()->create([
            'key' => 'version',
            'value' => '1.0.0',
        ]);

        MultiTenancy::setTenant($tenant);

        $metadata = MultiTenancy::getTenantDatabaseMetadata();

        expect($metadata)->toHaveCount(1);
        expect($metadata[0]['name'])->toBe('test_tenant_db');
        expect($metadata[0]['metadata']['version'])->toBe('1.0.0');
        expect($metadata[0]['connection_info']['driver'])->toBe('sqlite');
    }

    /** @test */
    public function it_handles_tenant_without_databases_gracefully()
    {
        // Create tenant without database
        $tenant = Tenant::create([
            'user_id' => 1,
            'name' => 'Test Company No DB',
        ]);

        MultiTenancy::setTenant($tenant);

        expect(MultiTenancy::hasTenant())->toBeTrue();
        expect(MultiTenancy::getTenant()->id)->toBe($tenant->id);
        expect(MultiTenancy::getCurrentConnectionName())->toBeNull();
    }

    /** @test */
    public function it_throws_exception_for_invalid_database_configuration()
    {
        $tenant = Tenant::create([
            'user_id' => 1,
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
    public function service_provider_registers_multitenancy_as_singleton()
    {
        $instance1 = app(MultiTenancy::class);
        $instance2 = app(MultiTenancy::class);

        expect($instance1)->toBe($instance2);
    }

    /** @test */
    public function facade_works_correctly()
    {
        expect(MultiTenancy::hasTenant())->toBeFalse();

        $tenant = $this->createTenantWithDatabase();
        MultiTenancy::setTenant($tenant);

        expect(MultiTenancy::hasTenant())->toBeTrue();
        expect(MultiTenancy::getTenantId())->toBe($tenant->id);
    }

    protected function createTenantWithDatabase(): Tenant
    {
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
                'prefix' => '',
            ],
        ]);

        return $tenant->fresh(['databases']);
    }

    protected function runPackageMigrations(): void
    {
        // Run tenant table migration
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('domain')->nullable();
            $table->string('subdomain')->nullable();
            $table->timestamps();
        });

        // Run tenant databases migration
        Schema::create('tenant_databases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_primary')->default(false);
            $table->json('connection_details');
            $table->timestamps();
        });

        // Run tenant database metadata migration
        Schema::create('tenant_database_metadata', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_database_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }
}
