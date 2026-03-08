<?php

namespace Worldesports\MultiTenancy\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Worldesports\MultiTenancy\Models\Tenant;
use Worldesports\MultiTenancy\Models\TenantDatabase;

class CreateTenantCommand extends Command
{
    public $signature = 'tenant:create
                        {user_id : The user ID to associate with the tenant}
                        {name : The tenant name}
                        {--domain= : Domain for automatic email-based detection (e.g., company.com)}
                        {--subdomain= : Subdomain for automatic URL-based detection (e.g., client1)}
                        {--db-name= : Database name for the tenant}
                        {--db-host=127.0.0.1 : Database host (not required for SQLite)}
                        {--db-port= : Database port (defaults: MySQL=3306, PostgreSQL=5432, SQL Server=1433)}
                        {--db-username= : Database username (not required for SQLite)}
                        {--db-password= : Database password (not required for SQLite)}
                        {--db-driver=mysql : Database driver (mysql, pgsql, sqlite, sqlsrv)}
                        {--primary : Mark the database as primary for this tenant}
                        {--create-db : Create the database if it doesn\'t exist}';

    public $description = 'Create a new tenant with database connection';

    public function handle(): int
    {
        $userId = $this->argument('user_id');
        $tenantName = $this->argument('name');

        // Validate user exists
        $userModel = config('multi-tenancy.user_model');
        if (! $userModel::find($userId)) {
            $this->error("User with ID {$userId} not found.");

            return self::FAILURE;
        }

        // Check if tenant already exists for user
        if (Tenant::where('user_id', $userId)->exists()) {
            $this->error("Tenant already exists for user ID {$userId}.");

            return self::FAILURE;
        }

        // Get database connection details
        $dbName = $this->option('db-name') ?? "tenant_{$userId}_db";
        $dbHost = $this->option('db-host');
        $dbDriver = $this->option('db-driver');
        $dbUsername = $this->option('db-username');
        $dbPassword = $this->option('db-password');

        // Set driver-specific defaults
        $dbPort = $this->option('db-port');
        if (! $dbPort) {
            $dbPort = match ($dbDriver) {
                'pgsql' => 5432,
                'sqlsrv' => 1433,
                'mysql' => 3306,
                'sqlite' => null,
                default => 3306
            };
        } else {
            $dbPort = (int) $dbPort;
        }

        // Driver-specific validation
        if ($dbDriver !== 'sqlite') {
            if (! $dbUsername || ! $dbPassword) {
                $this->error('Database username and password are required for '.$dbDriver.' driver.');

                return self::FAILURE;
            }
        }

        // Validate database name format
        if (! $this->validateDatabaseName($dbName)) {
            return self::FAILURE;
        }

        // Validate connection details format
        $validator = Validator::make([
            'driver' => $dbDriver,
            'host' => $dbHost,
            'port' => $dbPort,
            'database' => $dbName,
            'username' => $dbUsername,
            'password' => $dbPassword,
        ], [
            'driver' => 'required|string|in:mysql,pgsql,sqlite,sqlsrv',
            'host' => 'required|string|max:255',
            'port' => 'required|integer|between:1,65535',
            'database' => 'required|string|max:64|regex:/^[a-zA-Z0-9_]+$/',
            'username' => 'required|string|max:32',
            'password' => 'required|string|min:1',
        ]);

        if ($validator->fails()) {
            $this->error('Validation failed:');
            foreach ($validator->errors()->all() as $error) {
                $this->line("  - {$error}");
            }

            return self::FAILURE;
        }

        // Test database server connection first
        $connectionDetails = [
            'driver' => $dbDriver,
            'host' => $dbHost,
            'port' => $dbPort,
            'database' => $dbName,
            'username' => $dbUsername,
            'password' => $dbPassword,
        ];

        if (! $this->validateDatabaseConnection($connectionDetails)) {
            return self::FAILURE;
        }

        try {
            // Create database if requested (skip for SQLite)
            if ($this->option('create-db') && $dbDriver !== 'sqlite') {
                $this->info('Creating database...');
                $this->createDatabase($dbHost, $dbPort ?? 3306, $dbUsername, $dbPassword, $dbName, $dbDriver);
            }

            // Test connection
            $this->info('Testing database connection...');
            $this->testConnection($dbHost, $dbPort ?? 3306, $dbUsername, $dbPassword, $dbName, $dbDriver);

            // Create tenant
            $this->info('Creating tenant...');
            $tenant = Tenant::create([
                'user_id' => $userId,
                'name' => $tenantName,
                'domain' => $this->option('domain'),
                'subdomain' => $this->option('subdomain'),
            ]);

            // Create tenant database with driver-specific connection details
            $connectionDetails = [
                'driver' => $dbDriver,
                'database' => $dbName,
            ];

            // Add driver-specific connection details
            if ($dbDriver !== 'sqlite') {
                $connectionDetails['host'] = $dbHost;
                $connectionDetails['port'] = (int) $dbPort;
                $connectionDetails['username'] = $dbUsername;
                $connectionDetails['password'] = $dbPassword;
                $connectionDetails['prefix'] = '';
            }

            // Add driver-specific configurations
            switch ($dbDriver) {
                case 'mysql':
                    $connectionDetails['charset'] = 'utf8mb4';
                    $connectionDetails['collation'] = 'utf8mb4_unicode_ci';
                    $connectionDetails['strict'] = true;
                    $connectionDetails['engine'] = null;
                    break;
                case 'pgsql':
                    $connectionDetails['charset'] = 'utf8';
                    $connectionDetails['schema'] = 'public';
                    $connectionDetails['sslmode'] = 'prefer';
                    break;
                case 'sqlsrv':
                    $connectionDetails['charset'] = 'utf8';
                    break;
            }

            $tenantDatabase = TenantDatabase::create([
                'tenant_id' => $tenant->id,
                'name' => $dbName,
                'connection_details' => $connectionDetails,
                'is_primary' => $this->option('primary') ?: true, // Default to primary if it's the first database
            ]);

            $this->info("✅ Tenant '{$tenantName}' created successfully!");
            $this->info("   - Tenant ID: {$tenant->id}");
            $this->info("   - Database: {$dbName}");
            $this->info("   - User ID: {$userId}");

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to create tenant: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    private function createDatabase(string $host, int $port, string $username, string $password, string $database, string $driver): void
    {
        $config = [
            'driver' => $driver,
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'password' => $password,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ];

        $connection = DB::connection()->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME) === $driver
            ? DB::connection()
            : DB::connection()->setPdo(new \PDO(
                "{$driver}:host={$host};port={$port}",
                $username,
                $password
            ));

        $connection->statement("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    private function testConnection(string $host, int $port, string $username, string $password, string $database, string $driver): void
    {
        $dsn = "{$driver}:host={$host};port={$port};dbname={$database}";
        $pdo = new \PDO($dsn, $username, $password);
        $pdo->query('SELECT 1');
    }

    /**
     * Validate database connection
     *
     * @used
     */
    private function validateDatabaseConnection(array $connectionDetails): bool
    {
        try {
            // Test connection without database first (for creation)
            $dsn = "{$connectionDetails['driver']}:host={$connectionDetails['host']};port={$connectionDetails['port']}";
            $pdo = new \PDO($dsn, $connectionDetails['username'], $connectionDetails['password']);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            $this->info('✅ Database server connection successful');

            return true;
        } catch (\PDOException $e) {
            $this->error("❌ Database connection failed: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Validate database name format
     *
     * @used
     */
    private function validateDatabaseName(string $dbName): bool
    {
        // Validate database name format
        if (! preg_match('/^[a-zA-Z0-9_]+$/', $dbName)) {
            $this->error('Database name can only contain letters, numbers, and underscores');

            return false;
        }

        if (strlen($dbName) > 64) {
            $this->error('Database name cannot exceed 64 characters');

            return false;
        }

        return true;
    }
}
