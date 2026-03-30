<?php

namespace Worldesports\MultiTenancy;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Worldesports\MultiTenancy\Models\Tenant;
use Worldesports\MultiTenancy\Models\TenantDatabase;

class MultiTenancy
{
    protected ?Tenant $tenant = null;

    protected ?int $tenantId = null;
    protected ?int $tenantDatabaseId = null;
    protected ?string $currentConnectionName = null;
    protected static array $connectionCache = [];
    protected ?string $originalConnection = null;
    public function setTenant(Tenant $tenant, ?int $databaseId = null): void
    {
        // Store original connection if not already stored
        if (! $this->originalConnection) {
            $this->originalConnection = Config::get('database.default');
        }

        $this->tenant = $tenant;
        $this->tenantId = $tenant->id;
        $this->tenantDatabaseId = null;

        // Choose database: explicit ID > primary flag > single database fallback
        $database = null;
        $tenant->loadMissing('databases');

        if ($databaseId !== null) {
            $database = $tenant->databases->firstWhere('id', $databaseId);
        }

        if (! $database) {
            $database = $tenant->primaryDatabase();
        }

        if (! $database && $tenant->databases->count() === 1) {
            $database = $tenant->databases->first();
        }

        if ($database) {
            $this->useDatabase($database);
        }
    }

    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function getTenantId(): ?int
    {
        return $this->tenantId;
    }

    public function getCurrentConnectionName(): ?string
    {
        return $this->currentConnectionName;
    }

    public function getCurrentDatabaseId(): ?int
    {
        return $this->tenantDatabaseId;
    }

    /**
     * Explicitly choose a tenant database and (optionally) switch the default connection.
     */
    public function useDatabase(TenantDatabase $tenantDatabase, bool $switchDefault = true): string
    {
        $connectionName = $this->setTenantDatabaseConnection($tenantDatabase);
        $this->tenantDatabaseId = $tenantDatabase->id;

        if ($switchDefault) {
            $this->switchToTenantConnection();
        }

        return $connectionName;
    }

    /**
     * Choose a tenant database by ID without changing the tenant context.
     */
    public function useDatabaseId(int $databaseId, bool $switchDefault = true): string
    {
        $database = TenantDatabase::find($databaseId);

        if (! $database) {
            throw new InvalidArgumentException("Tenant database with ID {$databaseId} not found.");
        }

        return $this->useDatabase($database, $switchDefault);
    }

    public function setTenantDatabaseConnection(TenantDatabase $tenantDatabase): string
    {
        $connectionName = 'tenant_connection_'.$tenantDatabase->id;

        // Check if connection is already cached
        $useCache = config('multi-tenancy.cache_connections', true);

        if ($useCache && isset(static::$connectionCache[$connectionName])) {
            if (Config::has("database.connections.$connectionName")) {
                $this->currentConnectionName = $connectionName;

                return $connectionName;
            }

            unset(static::$connectionCache[$connectionName]);
        }

        $connectionDetails = $tenantDatabase->connection_details;

        $this->validateConnectionDetails($connectionDetails);

        $config = [
            'driver' => $connectionDetails['driver'],
        ];

        // Driver-specific configuration
        switch ($connectionDetails['driver']) {
            case 'sqlite':
                $config['database'] = $connectionDetails['database'];
                break;

            case 'pgsql':
                $config['host'] = $connectionDetails['host'] ?? '127.0.0.1';
                $config['port'] = $connectionDetails['port'] ?? '5432';
                $config['database'] = $connectionDetails['database'];
                $config['username'] = $connectionDetails['username'];
                $config['password'] = $connectionDetails['password'];
                $config['charset'] = $connectionDetails['charset'] ?? 'utf8';
                $config['prefix'] = $connectionDetails['prefix'] ?? '';
                $config['schema'] = $connectionDetails['schema'] ?? 'public';
                $config['sslmode'] = $connectionDetails['sslmode'] ?? 'prefer';
                break;

            case 'sqlsrv':
                $config['host'] = $connectionDetails['host'] ?? '127.0.0.1';
                $config['port'] = $connectionDetails['port'] ?? '1433';
                $config['database'] = $connectionDetails['database'];
                $config['username'] = $connectionDetails['username'];
                $config['password'] = $connectionDetails['password'];
                $config['charset'] = $connectionDetails['charset'] ?? 'utf8';
                $config['prefix'] = $connectionDetails['prefix'] ?? '';
                break;

            case 'mysql':
            default:
                $config['host'] = $connectionDetails['host'] ?? '127.0.0.1';
                $config['port'] = $connectionDetails['port'] ?? '3306';
                $config['database'] = $connectionDetails['database'];
                $config['username'] = $connectionDetails['username'];
                $config['password'] = $connectionDetails['password'];
                $config['charset'] = $connectionDetails['charset'] ?? 'utf8mb4';
                $config['collation'] = $connectionDetails['collation'] ?? 'utf8mb4_unicode_ci';
                $config['prefix'] = $connectionDetails['prefix'] ?? '';
                $config['strict'] = $connectionDetails['strict'] ?? true;
                $config['engine'] = $connectionDetails['engine'] ?? null;
                break;

        }

        Config::set("database.connections.$connectionName", $config);

        // Purge existing connection if it exists
        try {
            DB::connection($connectionName);
            DB::purge($connectionName);
        } catch (\Exception $e) {
            // Connection doesn't exist yet, which is fine
        }

        // Cache the connection
        if ($useCache) {
            static::$connectionCache[$connectionName] = true;
        }

        $this->currentConnectionName = $connectionName;

        return $connectionName;
    }

    /**
     * Get the connection name for a tenant database without mutating state.
     * This is safe to use in query scopes and other places where side effects should be avoided.
     */
    public function getConnectionNameForDatabase(TenantDatabase $tenantDatabase): string
    {
        return 'tenant_connection_'.$tenantDatabase->id;
    }

    public function switchToTenantConnection(): void
    {
        if ($this->currentConnectionName) {
            Config::set('database.default', $this->currentConnectionName);
        }
    }

    public function switchToMainConnection(): void
    {
        $mainConnection = $this->originalConnection ?? config('multi-tenancy.main_connection', 'mysql');
        Config::set('database.default', $mainConnection);
        $this->currentConnectionName = null;
    }

    public function purgeConnections(): void
    {
        foreach (array_keys(static::$connectionCache) as $connectionName) {
            try {
                DB::purge($connectionName);
            } catch (\Exception $e) {
                // Ignore purge errors
            }
        }
        static::$connectionCache = [];
    }

    public function resetTenant(): void
    {
        $this->switchToMainConnection();
        $this->tenant = null;
        $this->tenantId = null;
        $this->currentConnectionName = null;
        $this->purgeConnections();
    }

    public function getTenantDatabases(): array
    {
        if (! $this->tenant) {
            return [];
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, \Worldesports\MultiTenancy\Models\TenantDatabase> $databases */
        $databases = $this->tenant->databases()
            ->with('metadata')
            ->get();

        return $databases->map(function ($database) {
            /** @var \Worldesports\MultiTenancy\Models\TenantDatabase $database */
            return [
                'id' => $database->id,
                'name' => $database->name,
                'safe_connection_details' => $database->safe_connection_details,
                'metadata' => $database->metadata->pluck('value', 'key')->toArray(),
            ];
        })
            ->toArray();
    }

    public function hasTenant(): bool
    {
        return $this->tenant !== null;
    }

    public function ensureTenantIsSet(): void
    {
        if (! $this->tenant) {
            throw new InvalidArgumentException('Tenant is not set.');
        }
    }

    public function getTenantDatabaseMetadata(): array
    {
        if (! $this->tenant) {
            return [];
        }

        $this->tenant->loadMissing('databases.metadata');

        $databases = [];

        foreach ($this->tenant->databases as $database) {
            $safeDetails = array_filter(
                $database->safe_connection_details,
                fn ($value) => $value !== null
            );

            $databases[] = [
                'id' => $database->id,
                'name' => $database->name,
                'connection_info' => $safeDetails,
                'metadata' => $database->metadata->pluck('value', 'key')->toArray(),
                'created_at' => $database->created_at,
            ];
        }

        return $databases;
    }

    public function echoPhrase(string $phrase): string
    {
        return $phrase;
    }

    private function validateConnectionDetails(array $connectionDetails): void
    {
        if (! isset($connectionDetails['driver'])) {
            throw new InvalidArgumentException('Database driver is required.');
        }

        $driver = $connectionDetails['driver'];

        if ($driver === 'sqlite') {
            if (empty($connectionDetails['database'])) {
                throw new InvalidArgumentException('Database name is required.');
            }

            return;
        }

        if (empty($connectionDetails['database'])) {
            throw new InvalidArgumentException('Database name is required.');
        }

        if (empty($connectionDetails['username'])) {
            throw new InvalidArgumentException('Database username is required.');
        }

        if (! array_key_exists('password', $connectionDetails)) {
            throw new InvalidArgumentException('Database password is required.');
        }
    }
}
