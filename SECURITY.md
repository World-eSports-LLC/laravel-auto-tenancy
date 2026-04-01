# Security Policy

## Reporting a Vulnerability

The security of this package is important to us. If you discover a security vulnerability, please report it responsibly rather than using the public issue tracker.

### Reporting Process

**Email:** Please send security vulnerability reports to **[security@worldesports.app](mailto:security@worldesports.app)**

Include the following information in your report:

- **Title:** Brief description of the vulnerability
- **Description:** Detailed explanation of the issue
- **Affected Versions:** Which version(s) are affected
- **Proof of Concept:** Steps to reproduce or code example (if applicable)
- **Impact:** Severity and potential impact (e.g., data exposure, unauthorized access)
- **Suggested Fix:** If you have a fix, please describe it

### Response Timeline

- **Initial Response:** Within 48 hours
- **Assessment:** We will assess the vulnerability and determine severity
- **Fix Development:** We aim to provide a patch within 7-14 days for critical issues
- **Disclosure:** We'll coordinate with you on a responsible disclosure timeline

### What to Expect

1. We will acknowledge receipt of your report
2. We will investigate and validate the vulnerability
3. We will develop a fix and release a patch version
4. We will provide credit to the reporter (unless you prefer anonymity)
5. We will publish a security advisory once the patch is released

## Security Best Practices

When using Laravel Auto Tenancy, please follow these security guidelines:

### 1. Protect Connection Details

- **Never commit** database credentials to version control
- Store credentials in `.env` files (and exclude from git)
- Use environment variables for all sensitive configuration
- Enable connection encryption in production:

```env
MULTI_TENANT_ENCRYPT=true
```

### 2. Validate User Access

Always verify user authorization before granting tenant access:

```php
use Worldesports\MultiTenancy\Facades\MultiTenancy;

// Check if user has access to the tenant
if (!MultiTenancy::userHasAccessToTenant($user, $tenant)) {
    abort(403, 'Unauthorized access to this tenant');
}
```

### 3. Secure Database Credentials

- Use strong, unique passwords for each tenant database
- Rotate credentials regularly
- Use database user accounts with minimal required privileges
- Avoid using root/admin accounts for tenant databases

### 4. Validate Subdomain Detection

If using subdomain-based tenant detection, ensure your web server is configured to validate allowed hostnames:

```php
// Use Laravel's TrustedHosts middleware
protected $middleware = [
    \Illuminate\Http\Middleware\TrustHosts::class,
    // ... other middleware
];

// In config/app.php
'trusted_hosts' => ['app.example.com', '*.example.com'],
```

### 5. Monitor Tenant Activities

Log important tenant operations:

```php
// Log tenant creation
Log::info("Tenant created: {$tenant->id} by user {$user->id}");

// Log database switches
Log::info("Switched to tenant database: {$database->id}");

// Log access denials
Log::warning("Unauthorized tenant access attempt: User {$user->id} → Tenant {$tenant->id}");
```

### 6. Regular Updates

Keep the package updated to receive security patches:

```bash
# Check for updates
composer outdated

# Update to latest version
composer update worldesports/laravel-auto-tenancy
```

### 7. Connection Validation

The package validates all database connections before use. Don't bypass these checks in production:

```php
// ✅ Good - uses package validation
MultiTenancy::setTenant($tenant);

// ❌ Avoid - directly accessing connection config
Config::set("database.connections.{$name}", $config);
```

### 8. Test Your Implementation

- Test tenant isolation thoroughly
- Verify data cannot leak between tenants
- Test edge cases and error scenarios
- Perform security review before deploying to production

## Supported Versions

| Version | Status | Support Until |
|---------|--------|---|
| 1.x | ✅ Supported | Ongoing |
| < 1.0 | ❌ Unsupported | N/A |

**Current Version:** 1.0.0+

Security patches will be released as:
- **Patch versions** (1.0.1, 1.0.2, etc.) for bug fixes and security patches
- **Minor versions** (1.1.0, 1.2.0, etc.) for new features
- **Major versions** (2.0.0) for breaking changes

### Version Support Policy

- Latest version receives all security patches
- Previous minor version receives critical security patches for 6 months
- Older versions receive critical security patches for 3 months
- End-of-life versions do not receive patches

## Security Features

### ✅ Built-in Protections

1. **Password Masking**
   - Passwords are excluded from model serialization by default
   - Safe connection details available via `$tenantDatabase->safe_connection_details`

2. **Connection Validation**
   - All database connections are validated before use
   - Invalid connections throw exceptions with clear error messages

3. **Host Header Validation**
   - Subdomain detection validates against allowed base domains
   - Prevents Host header injection attacks

4. **Database Isolation**
   - Each tenant uses a separate database connection
   - No cross-tenant data leakage

5. **Encryption Support**
   - Optional encryption for connection details in database
   - Enable via `MULTI_TENANT_ENCRYPT=true`

6. **Access Control**
   - Built-in authorization checks for tenant access
   - `userHasAccessToTenant()` method for validation

## Known Limitations

1. **SQL Injection Prevention**
   - Always use parameterized queries and Laravel's query builder
   - Never concatenate user input into raw SQL

2. **Connection Pooling**
   - In long-lived worker processes (Octane, queues), connections are reset after each request
   - Prevents connection leakage between requests

3. **Driver Differences**
   - Some features may behave differently across database drivers (MySQL, PostgreSQL, SQLite, SQL Server)
   - Test thoroughly with your chosen driver

## Auditing and Logging

Enable logging to track tenant operations:

```php
// In your application's logging setup
'channels' => [
    'tenant' => [
        'driver' => 'single',
        'path' => storage_path('logs/tenant.log'),
        'level' => 'info',
    ],
],

// Log tenant operations
Log::channel('tenant')->info('Tenant created', [
    'tenant_id' => $tenant->id,
    'user_id' => $user->id,
    'database' => $database->name,
]);
```

## Dependencies Security

This package depends on Laravel and related packages. We recommend:

1. Keep Laravel updated to the latest LTS version
2. Run `composer audit` regularly to check for vulnerable dependencies
3. Enable GitHub Dependabot for automated security updates
4. Review security advisories in [Laravel Security Releases](https://laravel.com/docs/releases)

## Questions or Concerns?

If you have security questions or need clarification, please email **[security@worldesports.app](mailto:security@worldesports.app)**.

---

**Last Updated:** April 1, 2026  
**Package:** Laravel Auto Tenancy v1.0.0+  
**Maintained by:** World eSports LLC

