<?php

namespace Worldesports\MultiTenancy\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Worldesports\MultiTenancy\Facades\MultiTenancy;
use Worldesports\MultiTenancy\Models\Tenant;
use Worldesports\MultiTenancy\Models\TenantDatabase;

class TenantMigrateCommand extends Command
{
    public $signature = 'tenant:migrate
                        {--tenant= : Specific tenant ID to migrate}
                        {--database= : Specific database ID to migrate}
                        {--path= : Path to tenant migrations}
                        {--fresh : Drop all tables and re-run migrations}
                        {--seed : Run seeders after migrations}
                        {--rollback : Rollback the last batch of migrations}
                        {--status : Show migration status}';

    public $description = 'Run migrations on tenant databases';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $databaseId = $this->option('database');

        try {
            if ($databaseId) {

                $database = TenantDatabase::find($databaseId);

                if (! $database instanceof TenantDatabase) {

                    $this->error("Database with ID $databaseId not found.");

                    return self::FAILURE;

                }

                return $this->runMigrationForDatabase($database);

            }

            if ($tenantId) {

                $tenant = Tenant::find($tenantId);

                if (! $tenant instanceof Tenant) {

                    $this->error("Tenant with ID $tenantId not found.");

                    return self::FAILURE;

                }

                return $this->runMigrationsForTenant($tenant);

            }

            // Run for all tenants
            $this->info('Running migrations for all tenants...');
            $tenants = Tenant::with('databases')->get();

            foreach ($tenants as $tenant) {
                $result = $this->runMigrationsForTenant($tenant);
                if ($result === self::FAILURE) {
                    return self::FAILURE;
                }
            }

            return self::SUCCESS;

        } catch (Exception $e) {
            $this->error("Migration failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    private function runMigrationsForTenant(Tenant $tenant): int
    {
        $this->info("Running migrations for tenant: $tenant->name (ID: $tenant->id)");

        /** @var TenantDatabase $database */
        foreach ($tenant->databases as $database) {
            $result = $this->runMigrationForDatabase($database);
            if ($result === self::FAILURE) {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    private function runMigrationForDatabase(TenantDatabase $database): int
    {
        $this->info("  Processing database: $database->name");

        try {
            // Set up tenant connection
            $connectionName = MultiTenancy::setTenantDatabaseConnection($database);

            // Test connection
            DB::connection($connectionName)->getPdo();

            // Determine migration command
            $command = 'migrate';
            $path = $this->resolveTenantMigrationPath();

            if (! $path) {
                return self::FAILURE;
            }

            $options = [
                '--database' => $connectionName,
                '--force' => true,
                '--path' => $path,
            ];

            if ($this->option('fresh')) {
                $command = 'migrate:fresh';
            } elseif ($this->option('rollback')) {
                $command = 'migrate:rollback';
            } elseif ($this->option('status')) {
                $command = 'migrate:status';
            }

            // Run migration
            $exitCode = Artisan::call($command, $options);

            if ($exitCode === 0) {
                $this->info("    ✅ Migrations completed for $database->name");

                // Run seeders if requested
                if ($this->option('seed')) {
                    $this->info("    🌱 Running seeders for $database->name");
                    Artisan::call('db:seed', ['--database' => $connectionName, '--force' => true]);
                }
            } else {
                $this->error("    ❌ Migration failed for $database->name");
                $this->line('    '.Artisan::output());

                return self::FAILURE;
            }

        } catch (Exception $e) {
            $this->error("    ❌ Error with $database->name: {$e->getMessage()}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function resolveTenantMigrationPath(): ?string
    {
        $path = $this->option('path') ?: config('multi-tenancy.tenant_migrations_path');

        if (! is_string($path) || $path === '') {
            $this->error('Tenant migration path is not configured. Set multi-tenancy.tenant_migrations_path or pass --path.');

            return null;
        }

        $resolvedPath = is_dir($path) ? $path : base_path($path);

        if (! is_dir($resolvedPath)) {
            $this->error("Tenant migration path does not exist: {$path}");

            return null;
        }

        return $resolvedPath;
    }
}
