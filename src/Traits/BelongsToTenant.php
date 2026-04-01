<?php

namespace Worldesports\MultiTenancy\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Worldesports\MultiTenancy\Facades\MultiTenancy;
use Worldesports\MultiTenancy\Models\Tenant;

/**
 * @used
 */
trait BelongsToTenant
{
    private const SCOPE_NAME = 'tenant';

    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (MultiTenancy::hasTenant()) {
                $connectionName = MultiTenancy::getCurrentConnectionName();
                if ($connectionName) {
                    $builder->getModel()->setConnection($connectionName);
                }
            }
        });

        // Automatically set tenant connection on model creation
        static::creating(function (Model $model) {
            if (MultiTenancy::hasTenant() && MultiTenancy::getCurrentConnectionName()) {
                $model->setConnection(MultiTenancy::getCurrentConnectionName());
            }
        });

        // Ensure tenant connection on model retrieval
        static::retrieved(function (Model $model) {
            if (MultiTenancy::hasTenant() && MultiTenancy::getCurrentConnectionName()) {
                $model->setConnection(MultiTenancy::getCurrentConnectionName());
            }
        });
    }

    public function getConnectionName()
    {
        if (MultiTenancy::hasTenant() && MultiTenancy::getCurrentConnectionName()) {
            return MultiTenancy::getCurrentConnectionName();
        }

        return parent::getConnectionName();
    }

    public function scopeForTenant(Builder $query, ?int $tenantId = null, ?int $databaseId = null): Builder
    {
        if ($tenantId) {
            // Query with a specific tenant's connection without changing global context
            $tenant = Tenant::find($tenantId);
            if ($tenant) {
                $database = $tenant->databases()
                    ->when($databaseId, fn ($q) => $q->whereKey($databaseId))
                    ->first();
                if ($database) {
                    $connectionName = MultiTenancy::getConnectionNameForDatabase($database);

                    // Ensure the connection configuration exists without altering global defaults
                    if (! Config::has("database.connections.$connectionName")) {
                        $this->ensureConnectionConfiguration($connectionName, $database->connection_details);
                    }

                    // Track the database context without switching globals
                    MultiTenancy::useDatabase($database, switchDefault: false);

                    $query->getModel()->setConnection($connectionName);

                    return $query->getModel()->newQuery();
                }
            }
        }

        if (MultiTenancy::hasTenant()) {
            $connectionName = MultiTenancy::getCurrentConnectionName();
            if ($connectionName) {
                $query->getModel()->setConnection($connectionName);

                return $query->getModel()->newQuery();
            }
        }

        return $query;
    }

    /**
     * Register a tenant database connection on the fly without mutating the active default.
     *
     * This method dynamically configures a database connection in Laravel's config
     * without changing the application's default connection. Useful for querying
     * specific tenant databases without affecting global context.
     *
     * Supports all Laravel database drivers: mysql, pgsql, sqlite, sqlsrv
     *
     * @param  string  $connectionName  Unique identifier for this connection (e.g., 'tenant_5')
     * @param  array  $details  Database connection details array with keys:
     *                          - driver (required): 'mysql', 'pgsql', 'sqlite', or 'sqlsrv'
     *                          - host: Database host (required except for SQLite)
     *                          - port: Database port (optional, uses driver defaults)
     *                          - database (required): Database name or file path
     *                          - username: Database user (required except for SQLite)
     *                          - password: Database password (required except for SQLite)
     *                          - charset: Character set (optional)
     *                          - collation: Collation (optional for MySQL)
     *
     * @throws InvalidArgumentException If required connection details are missing
     */
    protected function ensureConnectionConfiguration(string $connectionName, array $details): void
    {
        if (! isset($details['driver'])) {
            throw new InvalidArgumentException('Database driver is required.');
        }

        $config = ['driver' => $details['driver']];

        switch ($details['driver']) {
            case 'sqlite':
                $config['database'] = $details['database'] ?? null;
                if (! $config['database']) {
                    throw new InvalidArgumentException('SQLite connection requires a database (path).');
                }
                break;

            case 'pgsql':
                $config = array_merge($config, [
                    'host' => $details['host'] ?? '127.0.0.1',
                    'port' => $details['port'] ?? '5432',
                    'database' => $details['database'] ?? null,
                    'username' => $details['username'] ?? null,
                    'password' => $details['password'] ?? null,
                    'charset' => $details['charset'] ?? 'utf8',
                    'prefix' => $details['prefix'] ?? '',
                    'schema' => $details['schema'] ?? 'public',
                    'sslmode' => $details['sslmode'] ?? 'prefer',
                ]);
                break;

            case 'sqlsrv':
                $config = array_merge($config, [
                    'host' => $details['host'] ?? '127.0.0.1',
                    'port' => $details['port'] ?? '1433',
                    'database' => $details['database'] ?? null,
                    'username' => $details['username'] ?? null,
                    'password' => $details['password'] ?? null,
                    'charset' => $details['charset'] ?? 'utf8',
                    'prefix' => $details['prefix'] ?? '',
                ]);
                break;

            case 'mysql':
            default:
                $config = array_merge($config, [
                    'host' => $details['host'] ?? '127.0.0.1',
                    'port' => $details['port'] ?? '3306',
                    'database' => $details['database'] ?? null,
                    'username' => $details['username'] ?? null,
                    'password' => $details['password'] ?? null,
                    'charset' => $details['charset'] ?? 'utf8mb4',
                    'collation' => $details['collation'] ?? 'utf8mb4_unicode_ci',
                    'prefix' => $details['prefix'] ?? '',
                    'strict' => $details['strict'] ?? true,
                    'engine' => $details['engine'] ?? null,
                ]);
                break;
        }

        if (empty($config['database'])) {
            throw new InvalidArgumentException('Database name is required.');
        }

        Config::set("database.connections.$connectionName", $config);

        // Purge any stale connection definition without switching the default connection
        try {
            DB::purge($connectionName);
        } catch (\Exception $e) {
            // ignore
        }
    }

    /**
     * Dedicated method for switching the application's tenant context.
     * Use this when you actually want to change the global tenant, not just query it.
     */
    public function switchTenantContext(?int $tenantId): void
    {
        if ($tenantId === null) {
            MultiTenancy::resetTenant();

            return;
        }

        $tenant = Tenant::find($tenantId);
        if (! $tenant) {
            throw new InvalidArgumentException("Tenant with ID {$tenantId} not found.");
        }

        MultiTenancy::setTenant($tenant);
    }

    /**
     * Bypass tenant scoping for administrative queries
     */
    public function scopeWithoutTenantScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('tenant');
    }

    /**
     * Ensure model is always saved to the correct tenant database
     */
    public function save(array $options = [])
    {
        if (MultiTenancy::hasTenant() && MultiTenancy::getCurrentConnectionName()) {
            $this->setConnection(MultiTenancy::getCurrentConnectionName());
        }

        return parent::save($options);
    }
}
