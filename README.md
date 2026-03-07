# Laravel Multi-Tenancy Package

[![Latest Version on Packagist](https://img.shields.io/packagist/v/worldesports/multi-tenancy.svg?style=flat-square)](https://packagist.org/packages/worldesports/multi-tenancy)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/worldesports/multi-tenancy/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/worldesports/multi-tenancy/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/worldesports/multi-tenancy/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/worldesports/multi-tenancy/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/worldesports/multi-tenancy.svg?style=flat-square)](https://packagist.org/packages/worldesports/multi-tenancy)

A Laravel package that enables **post-authentication multi-tenancy** for any Laravel application. Simply install the package and your Laravel app becomes multi-tenant with automatic tenant detection and database switching.

## Features

- **Post-authentication multi-tenancy**: Tenants are automatically detected and switched after user login
- **Multiple database support**: Each tenant can have separate database connections
- **Zero configuration**: Install and it works - no manual setup required
- **Automatic tenant switching**: Middleware automatically detects and switches tenant context
- **Model scoping**: Traits for automatic tenant-aware model queries
- **Database isolation**: Complete separation between tenant data
- **Multi-driver support**: MySQL, PostgreSQL, SQLite, and SQL Server
- **Connection caching**: Optimized database connection management
- **Encryption support**: Optional encryption for sensitive connection details
- **Comprehensive commands**: Full set of Artisan commands for tenant management
- **Event listeners**: Automatic tenant creation and cleanup
- **Security features**: Access control and validation
- **Testing suite**: Comprehensive test coverage

## Installation

You can install the package via composer:

```bash
composer require worldesports/multi-tenancy
```

Quick installation with setup:

```bash
php artisan tenant:install --migrate
```

Or manual installation:

```bash
# Publish and run the migrations
php artisan vendor:publish --tag="multi-tenancy-migrations"
php artisan migrate

# Optionally, publish the config file
php artisan vendor:publish --tag="multi-tenancy-config"
```

The published config file (`config/multi-tenancy.php`) contains:

```php
<?php

return [
    // Your User model - automatically detects the model used by your auth system
    'user_model' => env('MULTI_TENANT_USER_MODEL', 'App\\Models\\User'),
    
    // Main database connection
    'main_connection' => env('DB_CONNECTION', 'mysql'),
    
    // Auto-create tenant on user registration (optional)
    'auto_create_tenant' => env('MULTI_TENANT_AUTO_CREATE', false),
    
    // Performance optimizations
    'cache_connections' => env('MULTI_TENANT_CACHE_CONNECTIONS', true),
    
    // Security features
    'encrypt_connection_details' => env('MULTI_TENANT_ENCRYPT', false),
    
    // ... more options
];
```

## Configuration

### 1. User Model Configuration

The package automatically works with your existing User model. If you're using a custom User model or different namespace:

```php
// In config/multi-tenancy.php
'user_model' => App\Models\CustomUser::class,

// Or via environment variable
MULTI_TENANT_USER_MODEL="App\\Models\\CustomUser"
```

### 2. Authentication System Compatibility

#### Laravel Breeze
```bash
# Standard Laravel Breeze setup
composer require laravel/breeze
php artisan breeze:install
npm install && npm run dev
php artisan migrate

# Then install multi-tenancy
composer require worldesports/multi-tenancy
php artisan tenant:install --migrate
```

#### Laravel Jetstream  
```bash
# Standard Jetstream setup
composer require laravel/jetstream
php artisan jetstream:install livewire
npm install && npm run build
php artisan migrate

# Then install multi-tenancy
composer require worldesports/multi-tenancy
php artisan tenant:install --migrate
```

#### Laravel Sanctum API
```bash
# For API-based applications
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate

# Then install multi-tenancy
composer require worldesports/multi-tenancy
php artisan tenant:install --migrate
```

#### Social Authentication
```bash
# With Laravel Socialite
composer require laravel/socialite
# Configure OAuth providers in config/services.php

# Then install multi-tenancy
composer require worldesports/multi-tenancy
php artisan tenant:install --migrate
```

## Configuration

### 1. Register the middleware (optional)

If you want to manually control when tenant switching happens, add the middleware to your routes:

```php
// In routes/web.php or routes/api.php
Route::middleware(['auth', 'tenant'])->group(function () {
    // Your tenant-aware routes here
});
```

### 2. Add traits to your models

For models that should be automatically scoped to the current tenant database:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Worldesports\MultiTenancy\Traits\BelongsToTenant;

class Post extends Model
{
    use BelongsToTenant; // Automatically uses tenant database
    
    // Your model code...
}
```

For models that have a `tenant_id` column and need tenant-based scoping:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Worldesports\MultiTenancy\Traits\TenantScoped;

class Document extends Model
{
    use TenantScoped; // Automatically scopes by tenant_id
    
    protected $fillable = ['title', 'content', 'tenant_id'];
}
```

## Usage

### Package Status and Management

```bash
# Show overall status
php artisan tenant:status

# Show detailed tenant list
php artisan tenant:status --list

# Show specific tenant details
php artisan tenant:status --tenant=1

# Test all database connections
php artisan tenant:status --connections
```

### Creating Tenants

Use the artisan command to create a new tenant. The package supports all major database drivers:

#### **MySQL Tenant** (Default)
```bash
php artisan tenant:create 1 "MySQL Company" \
  --db-name=tenant_mysql_db \
  --db-driver=mysql \
  --db-host=127.0.0.1 \
  --db-port=3306 \
  --db-username=mysql_user \
  --db-password=secret \
  --create-db
```

#### **PostgreSQL Tenant**  
```bash
php artisan tenant:create 2 "PostgreSQL Company" \
  --db-name=tenant_postgres_db \
  --db-driver=pgsql \
  --db-host=127.0.0.1 \
  --db-port=5432 \
  --db-username=postgres_user \
  --db-password=secret \
  --create-db
```

#### **SQLite Tenant**
```bash
php artisan tenant:create 3 "SQLite Company" \
  --db-name=/path/to/tenant.sqlite \
  --db-driver=sqlite
  # Note: SQLite doesn't need username/password/host/port
```

#### **SQL Server Tenant**
```bash
php artisan tenant:create 4 "SQL Server Company" \
  --db-name=tenant_sqlserver_db \
  --db-driver=sqlsrv \
  --db-host=127.0.0.1 \
  --db-port=1433 \
  --db-username=sa \
  --db-password=secret \
  --create-db
```

### Multi-Driver Support

Your Laravel application can have **tenants using different database drivers simultaneously**:

- **Tenant 1**: Uses MySQL on server A
- **Tenant 2**: Uses PostgreSQL on server B  
- **Tenant 3**: Uses SQLite local file
- **Tenant 4**: Uses SQL Server on server C

The package automatically handles driver-specific configurations and optimizations.

### Database Management

```bash
# Run migrations on all tenant databases
php artisan tenant:migrate

# Run migrations on specific tenant
php artisan tenant:migrate --tenant=1

# Run migrations on specific database
php artisan tenant:migrate --database=1

# Fresh migrations with seeding
php artisan tenant:migrate --fresh --seed

# Seed tenant databases
php artisan tenant:seed

# Seed with specific seeder
php artisan tenant:seed --class=UserSeeder
```

### Tenant Cleanup

```bash
# Cleanup tenant (keeps database)
php artisan tenant:cleanup 1

# Cleanup and drop database (DANGEROUS!)
php artisan tenant:cleanup 1 --drop-database

# Skip confirmation
php artisan tenant:cleanup 1 --drop-database --force
```

### Working with Tenants in Code

```php
use Worldesports\MultiTenancy\Facades\MultiTenancy;
use Worldesports\MultiTenancy\Models\Tenant;

// Get current tenant
$tenant = MultiTenancy::getTenant();

// Manually set a tenant
$tenant = Tenant::find(1);
MultiTenancy::setTenant($tenant);

// Check if tenant is set
if (MultiTenancy::hasTenant()) {
    // Tenant is active, queries will use tenant database
}

// Switch back to main connection
MultiTenancy::switchToMainConnection();

// Reset tenant context completely
MultiTenancy::resetTenant();

// Purge all cached connections
MultiTenancy::purgeConnections();
```

### Automatic Tenant Detection

The package automatically detects and switches to the user's tenant upon login. No manual configuration required!

When a user logs in (through **any authentication system**):
1. The package looks up their tenant record
2. Automatically switches to the tenant's database connection
3. All subsequent queries use the tenant database

**Works with any authentication flow:**
- 📱 **Web login forms** (Breeze, Jetstream)
- 🔗 **Social media login** (Google, Facebook, GitHub via Socialite)  
- 🔑 **API token authentication** (Sanctum)
- 🎫 **SSO/SAML authentication**
- 🔐 **Custom authentication systems**
- 👥 **Multi-guard authentication**

The package listens to Laravel's standard `Login` event, so it works regardless of how the user authenticates.

### Using the Middleware

The `SetTenant` middleware automatically resolves the tenant for authenticated users:

```php
// The middleware is automatically applied after user authentication
// You can also manually apply it to specific routes
Route::middleware(['auth', \Worldesports\MultiTenancy\Middleware\SetTenant::class])
    ->get('/dashboard', [DashboardController::class, 'index']);

// With error handling options
Route::middleware(['auth', \Worldesports\MultiTenancy\Middleware\SetTenant::class . ':error'])
    ->get('/api/data', [ApiController::class, 'index']);
```

Middleware options:
- `redirect` (default): Redirect with error message
- `error`: Return JSON error response
- `ignore`: Log error but continue

## Advanced Configuration

### Environment Variables

```env
# Auto-create tenant on user registration
MULTI_TENANT_AUTO_CREATE=true

# Cache database connections for performance
MULTI_TENANT_CACHE_CONNECTIONS=true

# Encrypt connection details in database
MULTI_TENANT_ENCRYPT=true
```

### Event Listeners

The package automatically registers these event listeners:

```php
// Auto-set tenant on user login
Event::listen(Login::class, SetTenantOnLogin::class);

// Auto-create tenant on user registration (optional)
Event::listen(Registered::class, CreateTenantOnRegistration::class);
```

### Security Features

```php
// Check if user has access to tenant
if (!MultiTenancy::userHasAccessToTenant($user, $tenant)) {
    throw new UnauthorizedException('Access denied');
}

// Sanitized connection details (excludes sensitive info)
$safeDetails = $tenantDatabase->safe_connection_details;
```

### Model Scoping Examples

```php
// Using BelongsToTenant trait
class Invoice extends Model
{
    use BelongsToTenant;
    
    // Automatically queries tenant database
    public static function recent()
    {
        return static::where('created_at', '>', now()->subDays(30))->get();
    }
}

// Using TenantScoped trait (for models with tenant_id)
class Order extends Model
{
    use TenantScoped;
    
    // Automatically scoped to current tenant
    public function scopeUnpaid($query)
    {
        return $query->where('paid', false);
    }
    
    // Bypass tenant scoping when needed
    public static function allTenantsOrders()
    {
        return static::withoutTenantScoping()->get();
    }
}
```

## Authentication Integration Examples

### Example 1: Social Media Authentication with Multi-Tenancy

```php
// In your SocialAuthController (using Laravel Socialite)
class SocialAuthController extends Controller
{
    public function redirectToProvider($provider)
    {
        return Socialite::driver($provider)->redirect();
    }

    public function handleProviderCallback($provider)
    {
        $socialUser = Socialite::driver($provider)->user();
        
        // Find or create user
        $user = User::firstOrCreate(
            ['email' => $socialUser->getEmail()],
            [
                'name' => $socialUser->getName(),
                'password' => bcrypt(Str::random(16)), // Random password for social users
            ]
        );

        // Log the user in
        Auth::login($user);
        
        // Multi-tenancy package automatically detects and switches tenant!
        // No additional code needed - the package listens to Login event
        
        return redirect()->intended('/dashboard');
    }
}
```

### Example 2: API Authentication with Sanctum

```php
// API route with automatic tenant switching
Route::middleware(['auth:sanctum'])->group(function () {
    // The SetTenant middleware automatically applies tenant context
    Route::get('/api/tenant-data', function (Request $request) {
        // All queries automatically use the user's tenant database
        $data = SomeModel::all(); // Automatically scoped to tenant
        return response()->json($data);
    });
});

// The package automatically handles tenant switching for API requests
```

### Example 3: Custom Authentication Guard

```php
// If using custom authentication guard
'guards' => [
    'custom' => [
        'driver' => 'session',
        'provider' => 'custom_users',
    ],
],

'providers' => [
    'custom_users' => [
        'driver' => 'eloquent',
        'model' => App\Models\CustomUser::class,
    ],
],

// In your config/multi-tenancy.php
'user_model' => App\Models\CustomUser::class,

// The package works automatically with any authentication guard!
```

### Example 4: Multi-Guard Authentication

```php
// For applications with multiple user types (admin, customer, etc.)
Route::middleware(['auth:web'])->group(function () {
    // Regular customer routes - automatic tenant switching
});

Route::middleware(['auth:admin'])->group(function () {
    // Admin routes - can bypass tenant scoping when needed
    Route::get('/admin/all-tenants', function () {
        return Tenant::withoutGlobalScopes()->get(); // See all tenants
    });
});
```

### Example 5: Automated Tenant Creation for New Users

```php
// Enable auto-tenant creation in config/multi-tenancy.php
'auto_create_tenant' => true,

// Or via environment variable
MULTI_TENANT_AUTO_CREATE=true

// Now when users register (through ANY method), they automatically get a tenant:
// - Social media registration
// - Email/password registration  
// - API registration
// - SSO registration
// - Any custom registration flow
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [worldesports](https://github.com/keithprinkey)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
