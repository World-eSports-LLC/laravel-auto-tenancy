<?php

namespace Worldesports\MultiTenancy;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Worldesports\MultiTenancy\Commands\CleanupTenantCommand;
use Worldesports\MultiTenancy\Commands\CreateTenantCommand;
use Worldesports\MultiTenancy\Commands\InstallMultiTenancyCommand;
use Worldesports\MultiTenancy\Commands\MultiTenancyCommand;
use Worldesports\MultiTenancy\Commands\TenantMigrateCommand;
use Worldesports\MultiTenancy\Commands\TenantSeedCommand;
use Worldesports\MultiTenancy\Listeners\CreateTenantOnRegistration;
use Worldesports\MultiTenancy\Listeners\SetTenantOnLogin;

class MultiTenancyServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('multi-tenancy')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_tenant_table')
            ->hasMigration('create_tenant_databases_table')
            ->hasMigration('create_tenant_database_metadata_table')
            ->hasMigration('add_automatic_detection_fields_to_tenants_table')
            ->hasMigration('add_is_primary_to_tenant_databases_table')
            ->hasCommand(MultiTenancyCommand::class)
            ->hasCommand(CreateTenantCommand::class)
            ->hasCommand(TenantMigrateCommand::class)
            ->hasCommand(TenantSeedCommand::class)
            ->hasCommand(CleanupTenantCommand::class)
            ->hasCommand(InstallMultiTenancyCommand::class);
    }

    public function register()
    {
        parent::register();

        $this->app->singleton(MultiTenancy::class, function ($app) {
            return new MultiTenancy;
        });
    }

    public function boot()
    {
        parent::boot();

        // Register event listeners
        Event::listen(
            Login::class,
            [SetTenantOnLogin::class, 'handle']
        );

        // Optional: Auto-create tenants on registration
        if (config('multi-tenancy.auto_create_tenant', false)) {
            Event::listen(
                Registered::class,
                [CreateTenantOnRegistration::class, 'handle']
            );
        }
    }
}
