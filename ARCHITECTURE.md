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

### Step 3: Find Tenant's Database
```php
// Tenant::primaryDatabase() looks for is_primary=true
$database = $tenant->primaryDatabase(); // Gets TenantDatabase
// Contains: mysql connection to 'league_a_production' database
```

### Step 4: Switch Connection
```php
// MultiTenancy.php → setTenant($tenant)
MultiTenancy::setTenant($tenant);

// 1. Reads $database->connection_details (mysql credentials)
// 2. Creates a Laravel config entry: database.connections.tenant_connection_1
// 3. Sets config('database.default') to 'tenant_connection_1'
// 4. All queries now use League A's database
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

2. **Secondary Strategy** (Optional): Auto-create tenant if none exists
   ```php
   if (!$tenant && config('multi-tenancy.auto_create_tenant')) {
       $tenant = createTenantForUser($user);
   }
   ```

### What It Does NOT Do

❌ Email domain detection (`keith@company.com` → Company tenant)
❌ Subdomain detection (`tenant1.app.com` → tenant1)

These were removed because:
- **Email domains are insecure** - any user with a `company.com` email could access tenant data
- **Subdomains contradict post-auth philosophy** - subdomain-based tenancy doesn't require authentication first
- **Your SaaS uses admin assignment** - not automatic detection

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


