## [1.0.0] - 2024-03-27

### Added
- Initial release of Laravel multi-tenancy package
- Post-authentication multi-tenancy with per-tenant database isolation
- Automatic tenant detection by email domain, subdomain, or user mapping
- Support for MySQL, PostgreSQL, SQLite, and SQL Server
- Model traits for tenant-aware scoping (BelongsToTenant, TenantScoped)
- Comprehensive Artisan commands for tenant management
- Middleware for automatic tenant switching
- Event listeners for tenant creation/deletion
- Full test coverage

### Features
- Multi-driver database support (MySQL, PostgreSQL, SQLite, SQL Server)
- Automatic tenant context switching after authentication
- Connection caching for performance
- Optional encryption for connection credentials
- Zero-configuration installation
- Security features: access control, connection validation, Host header validation

[1.0.0]: https://github.com/KeithPrinkey-ops/laravel-multi-tenancy/releases/tag/v1.0.0
