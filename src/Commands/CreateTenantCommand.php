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
                        {user_id : The ID of the user to associate with the tenant}
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
                        {--create-db : Create the database if it doesn\'t exist}
                        {--root-username= : Root/admin username for database creation (required if --create-db is used for non-SQLite)}
                        {--root-password= : Root/admin password for database creation (required if --create-db is used for non-SQLite)}
                        {--force : Skip the creation confirmation prompt}';

    public $description = 'Create a new tenant with database connection';

    public function handle(): int
    {
        $userInput = (int) $this->argument('user_id');
        $tenantName = $this->argument('name');

        // Resolve user by primary key
        $userModel = config('multi-tenancy.user_model');
        $user = $userModel::find($userInput);

        if (! $user) {
            $this->error("User with ID {$userInput} not found.");
            return self::FAILURE;
        }

        $userId = $user->getKey();

        // Check if tenant already exists for this user
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
        if (! $this->validateDatabaseName($dbName, $dbDriver)) {
            return self::FAILURE;
        }

        // Validate connection details format
        $rules = [
            'driver' => 'required|string|in:mysql,pgsql,sqlite,sqlsrv',
            'database' => $dbDriver === 'sqlite'
                ? 'required|string'
                : 'required|string|max:64|regex:/^[a-zA-Z0-9_]+$/',
        ];

        if ($dbDriver !== 'sqlite') {
            $rules = array_merge($rules, [
                'host' => 'required|string|max:255',
                'port' => 'required|integer|between:1,65535',
                'username' => 'required|string|max:32',
                'password' => 'required|string|min:1',
            ]);
        }

        $validator = Validator::make([
            'driver' => $dbDriver,
            'host' => $dbHost,
            'port' => $dbPort,
            'database' => $dbName,
            'username' => $dbUsername,
            'password' => $dbPassword,
        ], $rules);

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

        if (! $this->confirmTenantCreation($tenantName, (string) $userId, $connectionDetails)) {
            if ($this->input->isInteractive()) {
                $this->info('Operation cancelled.');

                return self::SUCCESS;
            }

            return self::FAILURE;
        }

        if (! $this->validateDatabaseConnection($connectionDetails)) {
            return self::FAILURE;
        }

        try {
            // Create database if requested
            if ($this->option('create-db')) {
                if ($dbDriver === 'sqlite') {
                    $this->info('SQLite database will auto-create on first connection.');
                } else {
                    // Validate root credentials provided
                    $rootUsername = $this->option('root-username');
                    $rootPassword = $this->option('root-password');

                    if (! $rootUsername || ! $rootPassword) {
                        $this->error('Root/admin username and password are required to create the database.');
                        $this->line('Use: --root-username=root --root-password=secret');

                        return self::FAILURE;
                    }

                    $this->info('Creating database with root credentials...');
                    if (! $this->createDatabase($dbDriver, $dbHost, $dbPort, $rootUsername, $rootPassword, $dbName)) {
                        $this->warn('⚠️  Database creation failed or database already exists. Continuing...');
                    }
                }
            }

            // Test connection
            $this->info('Testing database connection...');
            $this->testConnection($dbHost, $dbPort, $dbUsername, $dbPassword, $dbName, $dbDriver);

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


    private function testConnection(string $host, ?int $port, ?string $username, ?string $password, string $database, string $driver): void
    {
        if ($driver === 'sqlite') {
            $dsn = "sqlite:{$database}";
            $pdo = new \PDO($dsn);
            $pdo->query('SELECT 1');

            return;
        }

        $dsn = "{$driver}:host={$host};port={$port};dbname={$database}";
        $pdo = new \PDO($dsn, $username, $password);
        $pdo->query('SELECT 1');
    }

    /**
     * Validate database connection
     *
     * Note: For MySQL/PostgreSQL/SQL Server, the database must be manually created
     * before running this command. This command does not have root/admin credentials.
     * SQLite databases are auto-created on first connection.
     *
     * @used
     */
    private function validateDatabaseConnection(array $connectionDetails): bool
    {
        try {
            if (($connectionDetails['driver'] ?? null) === 'sqlite') {
                return true;
            }

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
    private function validateDatabaseName(string $dbName, string $driver): bool
    {
        if ($driver === 'sqlite') {
            if ($dbName === '') {
                $this->error('SQLite database name/path cannot be empty');

                return false;
            }

            return true;
        }

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

    private function confirmTenantCreation(string $tenantName, string $userId, array $connectionDetails): bool
    {
        $this->warn("You are about to create tenant '{$tenantName}' for user ID {$userId}.");
        $this->line('The connection details are as follows:');
        $this->table(
            ['Setting', 'Value'],
            $this->buildConfirmationRows($connectionDetails)
        );

        if ($this->option('force')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            $this->error('Tenant creation requires confirmation in non-interactive mode. Re-run the command with --force to skip the prompt.');

            return false;
        }

        return $this->confirm('Are you sure you want to make this tenant?', false);
    }

    private function buildConfirmationRows(array $connectionDetails): array
    {
        $rows = [
            ['Driver', $connectionDetails['driver'] ?? 'N/A'],
            ['Database', $connectionDetails['database'] ?? 'N/A'],
        ];

        if (($connectionDetails['driver'] ?? null) !== 'sqlite') {
            $rows[] = ['Host', $connectionDetails['host'] ?? 'N/A'];
            $rows[] = ['Port', (string) ($connectionDetails['port'] ?? 'N/A')];
            $rows[] = ['Username', $connectionDetails['username'] ?? 'N/A'];
            $rows[] = ['Password', empty($connectionDetails['password']) ? '[not provided]' : '[hidden]'];
        }

        return $rows;
    }

    /**
     * Create a database using root/admin credentials
     * Supports MySQL, PostgreSQL, and SQL Server
     */
    private function createDatabase(string $driver, string $host, ?int $port, string $rootUsername, string $rootPassword, string $dbName): bool
    {
        try {
            switch ($driver) {
                case 'mysql':
                    return $this->createMysqlDatabase($host, $port ?? 3306, $rootUsername, $rootPassword, $dbName);

                case 'pgsql':
                    return $this->createPostgresqlDatabase($host, $port ?? 5432, $rootUsername, $rootPassword, $dbName);

                case 'sqlsrv':
                    return $this->createSqlServerDatabase($host, $port ?? 1433, $rootUsername, $rootPassword, $dbName);

                default:
                    $this->error("Database creation not supported for driver: {$driver}");
                    return false;
            }
        } catch (\Exception $e) {
            $this->error("Database creation error: {$e->getMessage()}");
            return false;
        }
    }

    private function createMysqlDatabase(string $host, int $port, string $username, string $password, string $dbName): bool
    {
        try {
            $dsn = "mysql:host={$host};port={$port}";
            $pdo = new \PDO($dsn, $username, $password);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            $sql = "CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
            $pdo->exec($sql);

            $this->info("✅ MySQL database '{$dbName}' created successfully");
            return true;
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') !== false) {
                $this->info("ℹ️  Database '{$dbName}' already exists");
                return true;
            }
            throw $e;
        }
    }

    private function createPostgresqlDatabase(string $host, int $port, string $username, string $password, string $dbName): bool
    {
        try {
            $dsn = "pgsql:host={$host};port={$port};dbname=postgres";
            $pdo = new \PDO($dsn, $username, $password);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // Check if database exists
            $result = $pdo->query("SELECT 1 FROM pg_database WHERE datname = '{$dbName}'");
            if ($result->rowCount() > 0) {
                $this->info("ℹ️  Database '{$dbName}' already exists");
                return true;
            }

            // Create database
            $sql = "CREATE DATABASE \"{$dbName}\" ENCODING 'UTF8'";
            $pdo->exec($sql);

            $this->info("✅ PostgreSQL database '{$dbName}' created successfully");
            return true;
        } catch (\PDOException $e) {
            throw $e;
        }
    }

    private function createSqlServerDatabase(string $host, int $port, string $username, string $password, string $dbName): bool
    {
        try {
            $dsn = "sqlsrv:Server={$host},{$port};Database=master";
            $pdo = new \PDO($dsn, $username, $password);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // Check if database exists
            $result = $pdo->query("SELECT 1 FROM sys.databases WHERE name = '{$dbName}'");
            if ($result->rowCount() > 0) {
                $this->info("ℹ️  Database '{$dbName}' already exists");
                return true;
            }

            // Create database
            $sql = "CREATE DATABASE [{$dbName}]";
            $pdo->exec($sql);

            $this->info("✅ SQL Server database '{$dbName}' created successfully");
            return true;
        } catch (\PDOException $e) {
            throw $e;
        }
    }
}
