<?php

namespace Worldesports\MultiTenancy\Tests;

use AllowDynamicProperties;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use Worldesports\MultiTenancy\MultiTenancyServiceProvider;

#[AllowDynamicProperties]
class TestCase extends Orchestra
{
    protected static ?string $testMigrationsPath = null;


    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Worldesports\\MultiTenancy\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function tearDown(): void
    {
        try {
            if (class_exists(\Worldesports\MultiTenancy\Facades\MultiTenancy::class)) {
                \Worldesports\MultiTenancy\Facades\MultiTenancy::resetTenant();
            }
        } catch (\Throwable $e) {
            // Avoid failing tests due to cleanup issues.
        }

        parent::tearDown();
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
        config()->set('multi-tenancy.main_connection', 'testing');
    }

    protected function defineDatabaseMigrations(): void
    {
        if (! in_array(\Worldesports\MultiTenancy\Tests\Concerns\UsesTestMigrations::class, class_uses_recursive(static::class), true)) {
            return;
        }

        self::$testMigrationsPath = self::ensureTestMigrations();
        $this->loadMigrationsFrom(self::$testMigrationsPath);
    }

    public static function migrationsPath(): string
    {
        return self::ensureTestMigrations();
    }

    protected static function ensureTestMigrations(): string
    {
        if (self::$testMigrationsPath) {
            return self::$testMigrationsPath;
        }

        $path = sys_get_temp_dir().'/multi-tenancy-test-migrations';
        if (! is_dir($path)) {
            mkdir($path, 0777, true);
        }

        $migrations = [
            '2000_01_01_000001_create_users_table.php' => self::usersMigrationStub(),
            '2000_01_01_000002_create_tenant_table.php' => __DIR__.'/../database/migrations/create_tenant_table.php.stub',
            '2000_01_01_000003_create_tenant_database.php' => __DIR__.'/../database/migrations/create_tenant_database.php.stub',
            '2000_01_01_000004_create_tenant_database_metadata_table.php' => __DIR__.'/../database/migrations/create_tenant_database_metadata_table.php.stub',
            '2000_01_01_000005_add_automatic_detection_fields_to_tenants_table.php' => __DIR__.'/../database/migrations/add_automatic_detection_fields_to_tenants_table.php.stub',
            '2000_01_01_000006_add_is_primary_to_tenant_databases_table.php' => __DIR__.'/../database/migrations/add_is_primary_to_tenant_databases_table.php.stub',
        ];

        foreach ($migrations as $filename => $source) {
            $target = $path.'/'.$filename;
            if (is_string($source) && file_exists($source)) {
                if (! file_exists($target)) {
                    copy($source, $target);
                }
            } else {
                if (! file_exists($target)) {
                    file_put_contents($target, $source);
                }
            }
        }

        self::$testMigrationsPath = $path;

        return $path;
    }

    protected static function usersMigrationStub(): string
    {
        return <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
PHP;
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
