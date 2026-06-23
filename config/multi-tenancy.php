<?php

// config for Worldesports/MultiTenancy
return [
    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The user model class that will be used to determine tenant relationships.
    | This should be set to your application's User model class.
    |
    */
    'user_model' => 'App\\Models\\User',

    /*
    |--------------------------------------------------------------------------
    | Main Database Connection
    |--------------------------------------------------------------------------
    |
    | The main database connection that will be used for tenant management
    | and when no tenant is active.
    |
    */
    'main_connection' => config('database.default', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Automatic Tenant Detection
    |--------------------------------------------------------------------------
    |
    | Configure how tenants are automatically detected when users log in.
    |
    */

    // Optional: auto-detect tenant by email domain (user@company.com -> Company tenant)
    // Disabled by default because post-authenticated user ownership is the safer default.
    'auto_detect_by_email' => false,

    // Email domains that should never be used for automatic tenant matching
    'generic_email_domains' => ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'icloud.com'],

    // Subdomain detection settings
    'subdomain' => [
        // Enable automatic tenant detection by subdomain (tenant1.app.com -> tenant1)
        'enabled' => false,

        // Base domain for subdomain validation (prevents Host header attacks)
        // Set this to your application's base domain, e.g., 'example.com'
        'base_domain' => null,

        // Subdomains to exclude from tenant detection
        'excluded' => ['www', 'app', 'api', 'admin'],
    ],

    // Auto-create tenant for users without existing tenant
    'auto_create_tenant' => false,

    // Auto-create database when creating tenant
    'auto_create_database' => false,

    /*
    |--------------------------------------------------------------------------
    | Default Tenant Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for automatically created tenants.
    |
    */
    'default_tenant_name' => 'Tenant for :name',

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for performance optimizations.
    |
    */
    'cache_connections' => true,

    /*
    |--------------------------------------------------------------------------
    | Tenant Migrations
    |--------------------------------------------------------------------------
    |
    | Migrations run by tenant:migrate must be separate from central app/package
    | migrations so tenant databases do not receive tenant-management tables.
    |
    */
    'tenant_migrations_path' => database_path('migrations/tenant'),

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Security-related configurations.
    |
    */
    // Deprecated legacy option for full-payload encryption support during reads.
    'encrypt_connection_details' => false,

    // Encrypt only the stored database password while leaving host/user/database readable.
    'encrypt_connection_password' => true,

    'security' => [
        'check_user_tenant_access' => true, // Verify user has access to tenant
        'allow_email_domain_access' => false, // Opt-in domain-based tenant access
        'log_tenant_switches' => true, // Log when tenants are switched
        'max_connection_attempts' => 3, // Max attempts for database connections
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | Route names used by the package.
    |
    */

    // Route to redirect users without a tenant (used by SetTenant middleware)
    // Set to null to disable redirection
    'tenant_setup_route' => null,
];
