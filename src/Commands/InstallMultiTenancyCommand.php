<?php

namespace Worldesports\MultiTenancy\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Worldesports\MultiTenancy\Support\AuthScaffoldingDetector;

class InstallMultiTenancyCommand extends Command
{
    public $signature = 'tenant:install
                        {--force : Overwrite existing files}
                        {--migrate : Run migrations after installation}
                        {--seed : Run seeders after migration}
                        {--skip-auth-check : Skip authentication scaffolding detection}';

    public $description = 'Install the multi-tenancy package';

    public function handle(): int
    {
        $this->info('🚀 Installing Laravel Multi-Tenancy Package');
        $this->line('');

        // Publish config
        $this->info('Publishing configuration file...');
        $configResult = Artisan::call('vendor:publish', [
            '--tag' => 'multi-tenancy-config',
            '--force' => $this->option('force'),
        ]);

        if ($configResult === 0) {
            $this->info('✅ Configuration file published');
        } else {
            $this->warn('⚠️  Configuration file may already exist (use --force to overwrite)');
        }

        // Publish migrations
        $this->info('Publishing migration files...');
        $migrationResult = Artisan::call('vendor:publish', [
            '--tag' => 'multi-tenancy-migrations',
            '--force' => $this->option('force'),
        ]);

        if ($migrationResult === 0) {
            $this->info('✅ Migration files published');
        } else {
            $this->warn('⚠️  Migration files may already exist (use --force to overwrite)');
        }

        // Run migrations if requested
        if ($this->option('migrate')) {
            $this->info('Running migrations...');
            $migrateResult = Artisan::call('migrate');

            if ($migrateResult === 0) {
                $this->info('✅ Migrations completed');
            } else {
                $this->error('❌ Migration failed');

                return self::FAILURE;
            }

            // Run seeders if requested
            if ($this->option('seed')) {
                $this->info('Running database seeders...');
                Artisan::call('db:seed');
                $this->info('✅ Seeders completed');
            }
        }

        $this->line('');
        $this->info('🎉 Multi-tenancy package installation completed!');
        $this->line('');
        $this->comment('Next steps:');
        $this->line('1. Configure your database connections in config/multi-tenancy.php');
        $this->line('2. Run migrations if you haven\'t: php artisan migrate');
        $this->line('3. Create your first tenant: php artisan tenant:create');
        $this->line('4. Add the BelongsToTenant trait to your models');
        $this->line('');
        $this->comment('Documentation: Check the README.md for usage examples');

        if (! $this->option('skip-auth-check') && ! $this->ensureAuthScaffolding()) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function ensureAuthScaffolding(): bool
    {
        $detector = app(AuthScaffoldingDetector::class);
        if ($detector->passes()) {
            $this->info('✅ Authentication scaffolding detected.');

            return true;
        }

        $this->warn('⚠️  Authentication scaffolding not detected.');
        foreach ($detector->issues() as $issue) {
            $this->line("  - {$issue}");
        }

        $this->line('');
        $this->comment('Install Laravel Jetstream (Livewire) to add authentication quickly:');
        $this->line('  composer require laravel/jetstream');
        $this->line('  php artisan jetstream:install livewire');
        $this->line('  npm install && npm run build');
        $this->line('  php artisan migrate');
        $this->line('');

        if ($this->confirm('Have you installed authentication scaffolding and want to continue?', false)) {
            if ($detector->passes()) {
                $this->info('✅ Authentication detected after confirmation.');

                return true;
            }

            $this->error('❌ Authentication scaffolding still not detected.');

            return false;
        }

        $this->error('❌ Aborting installation until authentication scaffolding is installed.');

        return false;
    }
}
