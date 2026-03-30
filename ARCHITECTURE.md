# Multi-Tenancy Architecture Guide

## Overview

This package provides **post-authentication multi-tenancy** for Laravel applications. It allows you to automatically switch database connections based on which tenant a user belongs to **after they log in**.

---

## Core Concept

### The Problem You're Solving

You have a SaaS application where:
- Multiple **organizations/leagues/companies** (Tenants) use the same app
- Each Tenant needs its **own isolated database** with separate data
- When a user logs in, you need to automatically switch to **their tenant's database**
- All queries should use that tenant's database for the session

### Your Use Case (League Manager)

```
User Registration/Admin Setup:
  1. Admin creates a League (Tenant record)
  2. Admin creates a database for that League (TenantDatabase record)
  3. Admin assigns Users to that League

User Login:
  1. User logs in → SetTenantOnLogin fires
  2. Listener finds User → Tenant (user_id→tenants.user_id)
  3. Finds Tenant → Database (primaryDatabase())
  4. Switches Laravel's database connection to that Tenant's database
  5. All User queries use Tenant database
```

---

## Data Model

### Three Core Models

#### 1. **Tenant**
```php
// Represents a tenant/league/organization
$tenant = Tenant::create([
    'user_id' => 1,                    // Owner/primary user
    'name' => 'League A',
    // Optionally: 'domain', 'subdomain' for hybrid setups
]);
```

**Key Point**: One `Tenant.user_id` can map to **ONE** tenant. But one **database can serve MANY users** from that tenant.

#### 2. **TenantDatabase**
```php
// Holds actual database connection credentials
$tenantDb = TenantDatabase::create([
    'tenant_id' => 1,
    'name' => 'league_a_db',
    'is_primary' => true,              // Primary database for tenant
    'connection_details' => [          // Actual DB credentials
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'league_a_production',
        'username' => 'user',
        'password' => 'pass',
    ],
]);
```

#### 3. **User** (Your App's User Model)
```php
// Standard Laravel User model - NOT managed by this package
$user = User::create([
    'name' => 'Keith',
    'email' => 'keith@example.com',
]);

// User has relationship: hasOne(Tenant) via user_id
```

---

## The Flow: How Database Switching Works

### Step 1: User Logs In
```php
// Laravel fires Illuminate\Auth\Events\Login
// SetTenantOnLogin listener catches it
```

### Step 2: Find User's Tenant
```php
// SetTenantOnLogin.php → handle() → findTenantByUserId()
$tenant = Tenant::where('user_id', $user->id)->first();
// Finds: Tenant with id=1, name='League A'
```

### Step 3: Choose Tenant Database
```php
// Primary pick order:
// 1) Explicit database ID passed to setTenant($tenant, $databaseId)
// 2) Tenant::primaryDatabase() (is_primary = true)
// 3) Single database fallback (if only one exists)
$database = $tenant->primaryDatabase(); // or the explicit one you passed
```

### Step 4: Switch Connection (or opt out)
```php
// MultiTenancy.php → setTenant($tenant, $databaseId = null)
MultiTenancy::setTenant($tenant);           // uses primary/first DB
MultiTenancy::setTenant($tenant, 5);        // use specific tenant DB ID

// Under the hood:
// 1. Builds config database.connections.tenant_connection_{db_id}
// 2. Switches config('database.default') to that tenant connection
// 3. Tracks the active tenant database ID
//
// Alternative (no global switch) for scoped queries only:
// MultiTenancy::useDatabase($database, switchDefault: false);
// (single active DB per request; you choose which one)
```

### Step 5: All Queries Use Tenant Database
```php
// User is now connected to League A's database
$posts = Post::all();  // Queries League A database
$users = User::all();  // Queries League A database
```

---

## How It Works with Multiple Users per Database

### Scenario: League A has 5 Users

```
League A (Tenant id=1)
├── User 1 (id=1)    ← tenants.user_id = 1
├── User 2 (id=2)    ← Some other way to associate
├── User 3 (id=3)
├── User 4 (id=4)
└── User 5 (id=5)

All 5 users have: Tenant.where('user_id', user.id) → Tenant(id=1)
All 5 users → League A database when logged in
```

**How does the package know User 2, 3, 4, 5 belong to the same league as User 1?**

**Answer**: It doesn't automatically. You (the SaaS admin) explicitly assign them:

```php
// In your app's User management UI or command:
$user2 = User::find(2);
$tenant = Tenant::find(1);

// Create relationship somehow (your responsibility):
// Option 1: If all users in tenant share user_id (unlikely)
$tenant->update(['user_id' => $user2->id]); // Overwrites - BAD

// Option 2: Create a pivot table (RECOMMENDED):
// users_tenants: user_id, tenant_id
// Then modify SetTenantOnLogin to look for any tenant association

// Option 3: Store tenant_id on users table:
// Users table: id, name, email, tenant_id
$user2->tenant_id = 1;
$user2->save();

// Then modify SetTenantOnLogin:
$tenant = Tenant::find($user->tenant_id);
```

---

## Current Architecture (Post-Auth Only)

### What SetTenantOnLogin Does NOW

1. **Primary Strategy**: Find tenant by `user_id`
   ```php
   $tenant = Tenant::where('user_id', $user->getKey())->first();
   ```

2. **Secondary Strategies (config gated)**:
   - Email domain detection
   - Subdomain detection (with base-domain validation)
   - Auto-create tenant (and optional default database) if none exists

3. **Tenant DB choice**: Uses `setTenant($tenant, $databaseId = null)` which follows the priority described above.

### Route Protection / Request Lifecycle

- `SetTenant` middleware resolves and sets the tenant (and DB).
- `tenant.required` middleware 403s if a tenant is not set after resolution.
- `SetTenant` resets the connection in a `finally` block to avoid tenant bleed in Octane/queues.

### Explicit Per-Database Queries Without Global Switch

```php
// Use a specific tenant DB but keep app default unchanged
$db = TenantDatabase::find(5);
MultiTenancy::useDatabase($db, switchDefault: false);

// Model scope (does not mutate global default)
Invoice::forTenant($tenantId = 10, $databaseId = 5)->get();
```

> Design note: At any point in a request, only one tenant database connection is active. The package is not yet intended for concurrent cross-database querying; instead you explicitly pick which tenant DB to use for a given operation.

---

## Your Responsibility

### 1. When User Registers
Decide: Will they join an existing Tenant or create a new one?

```php
// Option A: Admin assigns user to existing tenant
$tenant = Tenant::find($request->tenant_id);
$user = User::create(['name' => 'Keith', ...]);
$tenant->update(['user_id' => $user->id]); // If 1:1 relationship

// Option B: User creates their own tenant
$user = User::create([...]);
$tenant = Tenant::create([
    'user_id' => $user->id,
    'name' => $request->organization_name,
]);

// Then create database for tenant
TenantDatabase::create([
    'tenant_id' => $tenant->id,
    'name' => 'my_tenant_db',
    'connection_details' => [...]
]);
```

### 2. Handle Many Users Per Tenant

You need to extend the package to support N:M relationships:

```php
// Create users_tenants pivot table
Schema::create('users_tenants', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained('users');
    $table->foreignId('tenant_id')->constrained('tenants');
    $table->enum('role', ['owner', 'admin', 'member']);
    $table->timestamps();
});

// Modify SetTenantOnLogin:
private function findTenantByUserId($user): ?Tenant
{
    // Get first tenant this user belongs to
    return Tenant::whereHas('users', function ($q) use ($user) {
        $q->where('users.id', $user->id);
    })->first();
    
    // Or: $user->tenants()->first() if User has belongsToMany
}
```

### 3. Migrate Tenant Databases

When creating tenants, migrate their schema:

```php
// After TenantDatabase::create()
MultiTenancy::setTenant($tenant);
Artisan::call('migrate', ['--database' => 'tenant_connection_1']);
MultiTenancy::switchToMainConnection();
```

---

## Authentication System Support

This package works with ANY authentication system:

✅ Laravel Breeze
✅ Laravel Jetstream
✅ Custom authentication
✅ Social authentication (Socialite)

Because it **only** listens to `Illuminate\Auth\Events\Login` - as long as your auth fires that event, multi-tenancy works.

---

## Custom User Models

The package supports custom User models via config:

```php
// config/multi-tenancy.php
'user_model' => 'App\\Models\\CustomUser',
```

Or:

```php
// config/multi-tenancy.php
'user_model' => env('MULTI_TENANT_USER_MODEL', 'App\\Models\\User'),
```

The listener dynamically loads and uses whatever User model you specify.

---

## Summary

| Concept | Meaning |
|---------|---------|
| **Tenant** | Logical business entity (League, Organization) - has user_id + databases |
| **TenantDatabase** | Actual MySQL/PostgreSQL database with connection credentials |
| **Database Switching** | At login, Laravel's `database.default` changes to tenant's connection |
| **Post-Auth** | Database is switched AFTER user authenticates (not before, not by subdomain) |
| **Your Responsibility** | Assign users to tenants, create tenant databases, handle N:M users-per-tenant |
