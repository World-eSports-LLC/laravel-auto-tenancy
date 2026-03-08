<?php

namespace Worldesports\MultiTenancy\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use Worldesports\MultiTenancy\MultiTenancyServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Worldesports\\MultiTenancy\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            MultiTenancyServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Set up User model for testing - use a simple test implementation
        config()->set('multi-tenancy.user_model', TestUser::class);

        // Create users table FIRST before package migrations (to satisfy FK constraints)
        \Illuminate\Support\Facades\Schema::create('users', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });

        // Run migrations
        $migration = include __DIR__.'/../database/migrations/create_tenant_table.php.stub';
        $migration->up();

        $migration = include __DIR__.'/../database/migrations/create_tenant_database.php.stub';
        $migration->up();

        $migration = include __DIR__.'/../database/migrations/create_tenant_database_metadata_table.php.stub';
        $migration->up();

        // Run new migrations
        $migration = include __DIR__.'/../database/migrations/add_is_primary_to_tenant_databases_table.php.stub';
        $migration->up();

        $migration = include __DIR__.'/../database/migrations/add_automatic_detection_fields_to_tenants_table.php.stub';
        $migration->up();
    }
}

// Simple test user model - only for package testing
class TestUser extends \Illuminate\Foundation\Auth\User
{
    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password'];

    // Simple factory method for tests
    public static function factory()
    {
        return new class
        {
            public function create(array $attributes = [])
            {
                return TestUser::create(array_merge([
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                    'password' => bcrypt('password'),
                ], $attributes));
            }
        };
    }
}
