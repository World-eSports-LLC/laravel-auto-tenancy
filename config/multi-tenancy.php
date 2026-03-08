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

    // Auto-detect tenant by email domain (user@company.com -> Company tenant)
    'auto_detect_by_email' => true,

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
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Security-related configurations.
    |
    */
    'encrypt_connection_details' => false,

    'security' => [
        'check_user_tenant_access' => true, // Verify user has access to tenant
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
