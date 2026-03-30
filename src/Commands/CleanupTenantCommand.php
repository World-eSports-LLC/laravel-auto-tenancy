<?php

namespace Worldesports\MultiTenancy\Commands;

use Exception;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Worldesports\MultiTenancy\Models\Tenant;
use Worldesports\MultiTenancy\Models\TenantDatabase;

class CleanupTenantCommand extends Command
{
    public $signature = 'tenant:cleanup
                        {tenant : The tenant ID to cleanup}
                        {--drop-database : Actually drop the database (use with caution)}
                        {--force : Skip confirmation prompts}';

    public $description = 'Cleanup tenant and optionally drop their database';

    public function handle(): int
    {
        $tenantId = $this->argument('tenant');
        $dropDatabase = $this->option('drop-database');
        $force = $this->option('force');

        $tenant = Tenant::with('databases')->find($tenantId);
        if (! $tenant) {
            $this->error("Tenant with ID $tenantId not found.");

            return self::FAILURE;
        }

        if (! $force) {
            $this->warn("⚠️  You are about to cleanup tenant: {$tenant->name} (ID: {$tenant->id})");
            if ($dropDatabase) {
                $this->error("⚠️  This will also DROP the tenant's databases permanently!");
            }

            if (! $this->confirm('Are you sure you want to continue?')) {
                $this->info('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        try {
            // Cleanup databases if requested
            if ($dropDatabase) {
                foreach ($tenant->databases as $database) {
                    $this->cleanupDatabase($database);
                }
            }

            // Delete tenant record (will cascade to databases and metadata)
            $tenant->delete();

            $this->info("✅ Tenant '{$tenant->name}' has been cleaned up successfully.");

            if ($dropDatabase) {
                $this->info('✅ All tenant databases have been dropped.');
            } else {
                $this->info('ℹ️  Tenant databases were not dropped. Use --drop-database to remove them.');
            }

            return self::SUCCESS;

        } catch (Exception $e) {
            $this->error("Cleanup failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    private function cleanupDatabase(TenantDatabase $database): void
    {
        $this->info("Dropping database: {$database->name}");

        try {
            $connectionDetails = $database->connection_details;
            if (! is_array($connectionDetails)) {
                throw new InvalidArgumentException('Invalid connection details format.');
            }

            $driver = (string) ($connectionDetails['driver'] ?? 'mysql');

            match ($driver) {
                'mysql' => $this->dropMysqlDatabase($connectionDetails),
                'pgsql' => $this->dropPgsqlDatabase($connectionDetails),
                'sqlsrv' => $this->dropSqlsrvDatabase($connectionDetails),
                'sqlite' => $this->dropSqliteDatabase($connectionDetails),
                default => throw new InvalidArgumentException("Unsupported database driver '{$driver}' for cleanup."),
            };

            $databaseName = (string) ($connectionDetails['database'] ?? $database->name);
            $this->info("  ✅ Database '{$databaseName}' dropped successfully.");

        } catch (Exception $e) {
            $this->error("  ❌ Failed to drop database '{$database->name}': {$e->getMessage()}");
            throw $e;
        }
    }

    private function dropMysqlDatabase(array $connectionDetails): void
    {
        $host = $this->requireConnectionValue($connectionDetails, 'host');
        $port = (string) ($connectionDetails['port'] ?? '3306');
        $username = $this->requireConnectionValue($connectionDetails, 'username');
        $password = (string) ($connectionDetails['password'] ?? '');
        $database = $this->requireSafeDatabaseName($connectionDetails);

        $pdo = new \PDO("mysql:host={$host};port={$port}", $username, $password);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('DROP DATABASE IF EXISTS '.$this->quoteMysqlIdentifier($database));
    }

    private function dropPgsqlDatabase(array $connectionDetails): void
    {
        $host = $this->requireConnectionValue($connectionDetails, 'host');
        $port = (string) ($connectionDetails['port'] ?? '5432');
        $username = $this->requireConnectionValue($connectionDetails, 'username');
        $password = (string) ($connectionDetails['password'] ?? '');
        $database = $this->requireSafeDatabaseName($connectionDetails);

        // Connect to an admin database so the target database can be dropped.
        $pdo = new \PDO("pgsql:host={$host};port={$port};dbname=postgres", $username, $password);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $identifier = $this->quotePgsqlIdentifier($database);
        $pdo->exec("SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '{$database}' AND pid <> pg_backend_pid()");
        $pdo->exec("DROP DATABASE IF EXISTS {$identifier}");
    }

    private function dropSqlsrvDatabase(array $connectionDetails): void
    {
        $host = $this->requireConnectionValue($connectionDetails, 'host');
        $port = (string) ($connectionDetails['port'] ?? '1433');
        $username = $this->requireConnectionValue($connectionDetails, 'username');
        $password = (string) ($connectionDetails['password'] ?? '');
        $database = $this->requireSafeDatabaseName($connectionDetails);

        $pdo = new \PDO("sqlsrv:Server={$host},{$port};Database=master", $username, $password);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $identifier = $this->quoteSqlsrvIdentifier($database);
        $pdo->exec("IF DB_ID(N'{$database}') IS NOT NULL BEGIN ALTER DATABASE {$identifier} SET SINGLE_USER WITH ROLLBACK IMMEDIATE; DROP DATABASE {$identifier}; END");
    }

    private function dropSqliteDatabase(array $connectionDetails): void
    {
        $databasePath = $this->requireConnectionValue($connectionDetails, 'database');
        if ($databasePath === ':memory:') {
            throw new InvalidArgumentException('Cannot drop an in-memory SQLite database.');
        }

        if (! file_exists($databasePath)) {
            return;
        }

        if (! is_file($databasePath)) {
            throw new InvalidArgumentException('SQLite database path is not a file.');
        }

        if (! unlink($databasePath)) {
            throw new InvalidArgumentException("Unable to delete SQLite database file '{$databasePath}'.");
        }
    }

    private function requireConnectionValue(array $connectionDetails, string $key): string
    {
        $value = (string) ($connectionDetails[$key] ?? '');
        if ($value === '') {
            throw new InvalidArgumentException("Missing required connection detail '{$key}'.");
        }

        return $value;
    }

    private function requireSafeDatabaseName(array $connectionDetails): string
    {
        $database = $this->requireConnectionValue($connectionDetails, 'database');

        if (! preg_match('/^[A-Za-z0-9_]+$/', $database)) {
            throw new InvalidArgumentException('Unsafe database name. Only letters, numbers, and underscores are allowed for cleanup.');
        }

        return $database;
    }

    private function quoteMysqlIdentifier(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }

    private function quotePgsqlIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }

    private function quoteSqlsrvIdentifier(string $identifier): string
    {
        return '['.str_replace(']', ']]', $identifier).']';
    }
}
