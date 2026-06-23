# Laravel Auto Tenancy

Post-authentication multi-tenancy for Laravel applications with runtime tenant connection resolution and automatic database switching after login.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/worldesports/laravel-auto-tenancy.svg?style=flat-square)](https://packagist.org/packages/worldesports/laravel-auto-tenancy) [![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/World-eSports-LLC/laravel-auto-tenancy/run-tests.yml?branch=remote&label=tests&style=flat-square)](https://github.com/World-eSports-LLC/laravel-auto-tenancy/actions?query=workflow%3Arun-tests+branch%3Aremote) [![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/World-eSports-LLC/laravel-auto-tenancy/fix-php-code-style-issues.yml?branch=remote&label=code%20style&style=flat-square)](https://github.com/World-eSports-LLC/laravel-auto-tenancy/actions?query=workflow%3A%22Fix+PHP+code+style+issues%22+branch%3Aremote) [![PHPStan](https://img.shields.io/github/actions/workflow/status/World-eSports-LLC/laravel-auto-tenancy/phpstan.yml?branch=remote&label=phpstan&style=flat-square)](https://github.com/World-eSports-LLC/laravel-auto-tenancy/actions?query=workflow%3APHPStan+branch%3Aremote) [![Total Downloads](https://img.shields.io/packagist/dt/worldesports/laravel-auto-tenancy.svg?style=flat-square)](https://packagist.org/packages/worldesports/laravel-auto-tenancy)

## What makes this package different?

Most Laravel multi-tenancy packages are commonly used in domain-first or subdomain-first workflows, where the tenant is identified from the request before or independently of user authentication.

`worldesports/laravel-auto-tenancy` is built for a different workflow:

1. The user logs in or registers first.
2. The application determines the tenant from the authenticated user.
3. The package chooses or builds the correct database connection at runtime for that tenant.
4. The application is automatically switched into the correct tenant database/context.

This makes it a strong fit for Laravel applications where tenancy is resolved **after authentication** rather than solely from the incoming request host.

### Example use case

Your app has a shared login screen for all users.

After authentication:

- user ID 1 should use the tenant/database provisioned for user ID 1
- user ID 2 should use the tenant/database provisioned for user ID 2
- tenant context is determined from the authenticated user by default
- the package switches the application into the correct tenant database/context

## Good Fit for This Package

Use this package if your app needs to:

- resolve tenancy after login or registration
- determine the tenant from the authenticated user
- choose or build the correct tenant database connection at runtime
- switch tenant databases automatically
- add tenancy to an existing Laravel auth flow without redesigning the app around domain-first tenancy
- integrate with existing multi-tenant systems

## When This May Not Be the Right Fit

If your application is primarily domain-first or subdomain-first and you need broader tenant bootstrapping beyond authenticated-user-driven database switching, another tenancy approach may be a better fit.

## Features

- **Post-authentication multi-tenancy**: Tenants are automatically detected and switched after user login
- **Runtime connection resolution**: The package chooses or builds the correct tenant connection for the authenticated user
- **Multiple database support**: Each tenant can use a separate database connection
- **Minimal configuration**: Install quickly with sensible defaults
- **Automatic tenant switching**: Middleware automatically resolves and switches tenant context
- **Model scoping**: Traits for tenant-aware model queries
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
composer require worldesports/laravel-auto-tenancy
```

Quick installation with setup:

```bash
php artisan tenant:install --force --migrate
```

If authentication is missing, the installer will prompt you to install Jetstream:

```bash
composer require laravel/jetstream
php artisan jetstream:install livewire
npm install && npm run build
php artisan migrate
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
    // Your User model
    'user_model' => App\\Models\\User::class,
    
    // Main database connection
    'main_connection' => config('database.default', 'mysql'),
    
    // Auto-create tenant on user registration (optional)
    'auto_create_tenant' => false,

    // Optional email-domain detection; disabled by default for security
    'auto_detect_by_email' => false,
    
    // Performance optimizations
    'cache_connections' => true,
    
    // Security features
    'encrypt_connection_details' => false,
    
    // ... more options
];
```

## Basic Usage

### 1. User Model Configuration

The package automatically works with your existing User model. If you're using a custom User model or different namespace:

```php
// In config/multi-tenancy.php
'user_model' => App\Models\CustomUser::class,
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
composer require worldesports/laravel-auto-tenancy
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
composer require worldesports/laravel-auto-tenancy
php artisan tenant:install --migrate
```

#### Laravel Sanctum API
```bash
# For API-based applications
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate

# Then install multi-tenancy
composer require worldesports/laravel-auto-tenancy
php artisan tenant:install --migrate
```

#### Social Authentication
```bash
# With Laravel Socialite
composer require laravel/socialite
# Configure OAuth providers in config/services.php

# Then install multi-tenancy
composer require worldesports/laravel-auto-tenancy
php artisan tenant:install --migrate
```

## Setup and Usage

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

# Test connections for one driver only
php artisan tenant:status --connections --driver=mysql
php artisan tenant:status --connections --driver=pgsql
php artisan tenant:status --connections --driver=sqlite
php artisan tenant:status --connections --driver=sqlsrv
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

# Test all tenant database connections
php artisan tenant:status --connections

# Test connections for one driver only
php artisan tenant:status --connections --driver=mysql
php artisan tenant:status --connections --driver=pgsql
php artisan tenant:status --connections --driver=sqlite
php artisan tenant:status --connections --driver=sqlsrv
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
use Worldesports\MultiTenancy\Models\TenantDatabase;

// Get current tenant
$tenant = MultiTenancy::getTenant();

// Manually set a tenant
$tenant = Tenant::find(1);
MultiTenancy::setTenant($tenant);

// Manually set a tenant and pick a specific tenant database
$database = TenantDatabase::find(5);
MultiTenancy::useDatabase($database); // switches default connection to this DB

// Or pass a database ID when setting the tenant (falls back to primary/first if null)
MultiTenancy::setTenant($tenant, $databaseId = 5);

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

### Querying for a specific tenant (and database) without changing the global context

```php
// Scope a model to a tenant (uses its primary DB)
Invoice::forTenant($tenantId = 10)->get();

// Scope a model to a specific tenant *database* without switching the app default
Invoice::forTenant($tenantId = 10, $databaseId = 22)->get();
```

> Note: The package keeps **one active tenant database at a time** per request/context. You can pick which tenant DB to use, but queries execute against a single selected database, not multiple concurrently.

### Tenant Resolution

By default, the package resolves tenants **after authentication** by matching the authenticated user's primary key to `tenants.user_id`. That is the recommended production path because it does not trust hostnames or email domains for tenant membership.

```bash
php artisan tenant:create 123 "Manual Tenant"
```

When user ID `123` logs in and the `tenant` middleware runs, the package loads that user's tenant, builds the tenant database connection from the stored `tenant_databases.connection_details`, and switches the request to that connection.

#### Optional Strategy: Email Domain Detection
Email-domain detection is available, but it is disabled by default. Enable it only if your application treats matching email domains as authorized tenant membership:

```bash
# Create tenant for a company
php artisan tenant:create 1 "ACME Corporation" --domain=acme.com
```

**Configuration:**
```php
'auto_detect_by_email' => true,
```

Also enable domain-based access if non-owner users should be authorized by exact email-domain match:

```php
'security' => [
    'allow_email_domain_access' => true,
],
```

#### Optional Strategy: Subdomain Detection
Subdomain detection is available, but disabled by default and should be paired with Laravel trusted-host protection:

```bash
# Create tenant with subdomain
php artisan tenant:create 2 "Client Portal" --subdomain=client1
```

**Configuration:**
```php
'subdomain' => [
    'enabled' => true,
    'base_domain' => 'example.com',
],
```

#### Optional Strategy: Auto-Create Tenants
Auto-create is disabled by default. For production v1 usage, prefer `tenant:create` because it explicitly provisions the tenant database credentials used for runtime switching:

```bash
php artisan tenant:create 123 "Manual Tenant"
```

**Configuration:**
```php
'auto_create_tenant' => true,
```

`tenant:create` remains the provisioning path for database credentials. Runtime requests do not use root/admin credentials.

### Using the Middleware

The `SetTenant` middleware resolves tenant context for authenticated users. It is non-enforcing by default; use `tenant.required` on routes that must have tenant context.

```php
// Apply the tenant middleware after auth on routes that should resolve tenant context
Route::middleware(['auth', \Worldesports\MultiTenancy\Middleware\SetTenant::class])
    ->get('/dashboard', [DashboardController::class, 'index']);

// With error handling options
Route::middleware(['auth', \Worldesports\MultiTenancy\Middleware\SetTenant::class . ':error'])
    ->get('/api/data', [ApiController::class, 'index']);

// Require a tenant to be present (403s if missing)
Route::middleware(['auth', 'tenant', 'tenant.required'])
    ->group(function () {
        Route::get('/account', [AccountController::class, 'show']);
    });
```

Middleware options:
- `ignore` (default): Continue when no tenant exists
- `error`: Return JSON error response when no tenant exists
- `redirect`: Redirect to `multi-tenancy.tenant_setup_route` when configured

## Advanced Configuration

### Common Configuration

After publishing `config/multi-tenancy.php`, common production settings include:

```php
'auto_create_tenant' => false,

'auto_detect_by_email' => false,

'subdomain' => [
    'enabled' => false,
    'base_domain' => null,
],

'tenant_migrations_path' => database_path('migrations/tenant'),

'cache_connections' => true,

'encrypt_connection_password' => true,

'security' => [
    'check_user_tenant_access' => true,
    'allow_email_domain_access' => false,
],
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

## Multi-User & Concurrent Session Support

### **✅ Multiple Users, Different Tenants**
Each authenticated request resolves its own tenant context:

```php
// User A logs in → uses the tenant database provisioned for User A
// User B logs in → uses the tenant database provisioned for User B
// User C logs in → uses the tenant database provisioned for User C

// All three users can be using the app simultaneously with different tenant databases!
```

### **✅ Request Isolation**
- Each request receives a fresh tenant context from the authenticated user
- No interference between concurrent users
- Tenant context is reset after the request to support long-lived workers
- Use the `tenant` middleware on authenticated web/API routes that need tenant data

### **✅ API & Web Support**
```php
// Web users (sessions)
Route::middleware(['auth:web', SetTenant::class])->group(function () {
    // Each web session gets its tenant context
});

// API users (tokens) 
Route::middleware(['auth:sanctum', SetTenant::class])->group(function () {
    // Each API request gets tenant context based on the authenticated user
});
```

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
        
        // The login listener can set tenant context for this request.
        // Keep the tenant middleware on authenticated routes for later requests.
        
        return redirect()->intended('/dashboard');
    }
}
```

### Example 2: API Authentication with Sanctum

```php
// API route with post-auth tenant switching
Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
    // The tenant middleware applies tenant context for the authenticated user
    Route::get('/api/tenant-data', function (Request $request) {
        // All queries automatically use the user's tenant database
        $data = SomeModel::all(); // Automatically scoped to tenant
        return response()->json($data);
    });
});

// Add tenant.required to routes that must fail closed when no tenant exists.
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
    // Add 'tenant' to regular customer routes that need tenant context
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

// When this optional listener is enabled, newly registered users get a tenant row:
// - Social media registration
// - Email/password registration  
// - API registration
// - SSO registration
// - Any custom registration flow
//
// Production apps should still provision tenant database credentials with tenant:create.
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Security Vulnerabilities

Please review [our security policy](SECURITY.md) on how to report security vulnerabilities.

## Credits

- [worldesports](https://github.com/keithprinkey)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
