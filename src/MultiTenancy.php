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

    protected ?string $currentConnectionName = null;

    protected static array $connectionCache = [];

    protected ?string $originalConnection = null;

    public function setTenant(Tenant $tenant): void
    {
        // Store original connection if not already stored
        if (! $this->originalConnection) {
            $this->originalConnection = Config::get('database.default');
        }

        $this->tenant = $tenant;
        $this->tenantId = $tenant->id;

        $defaultDatabase = $tenant->primaryDatabase();
        if ($defaultDatabase) {
            $this->setTenantDatabaseConnection($defaultDatabase);
            $this->switchToTenantConnection();
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

    public function setTenantDatabaseConnection(TenantDatabase $tenantDatabase): string
    {
        $connectionName = 'tenant_connection_'.$tenantDatabase->id;

        // Check if connection is already cached
        if (isset(static::$connectionCache[$connectionName])) {
            $this->currentConnectionName = $connectionName;

            return $connectionName;
        }

        $connectionDetails = $tenantDatabase->connection_details;

        if (! isset($connectionDetails['driver'])) {
            throw new InvalidArgumentException('Database driver is required.');
        }

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
        static::$connectionCache[$connectionName] = true;

        $this->currentConnectionName = $connectionName;

        return $connectionName;
    }

    public function switchToTenantConnection(): void
    {
        if ($this->currentConnectionName) {
            Config::set('database.default', $this->currentConnectionName);
            DB::reconnect($this->currentConnectionName);
        }
    }

    public function switchToMainConnection(): void
    {
        $mainConnection = $this->originalConnection ?? config('multi-tenancy.main_connection', 'mysql');
        Config::set('database.default', $mainConnection);
        DB::reconnect($mainConnection);
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
                'connection_details' => $database->connection_details,
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

        $metaData = [];
        foreach ($this->tenant->databases as $database) {
            $connectionDetails = $database->connection_details;
            $metadata = $database->metadata->pluck('value', 'key')->toArray();

            $metaData[] = [
                'id' => $database->id,
                'name' => $database->name,
                'connection_info' => [
                    'driver' => $connectionDetails['driver'] ?? null,
                    'host' => $connectionDetails['host'] ?? null,
                    'port' => $connectionDetails['port'] ?? null,
                    'database' => $connectionDetails['database'] ?? null,
                    'username' => $connectionDetails['username'] ?? null,
                ],
                'metadata' => $metadata,
                'created_at' => $database->created_at,
            ];
        }

        return $metaData;
    }

    public function echoPhrase(string $phrase): string
    {
        return $phrase;
    }
}
