<?php

namespace Worldesports\MultiTenancy\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Worldesports\MultiTenancy\MultiTenancy
 *
 * @method static void setTenant(Tenant $tenant, ?int $databaseId = null)
 * @method static ?Tenant getTenant()
 * @method static ?int getTenantId()
 * @method static bool hasTenant()
 * @method static void resetTenant()
 * @method static void switchToMainConnection()
 * @method static string|null getCurrentConnectionName()
 * @method static ?int getCurrentDatabaseId()
 * @method static string useDatabase(TenantDatabase $tenantDatabase, bool $switchDefault = true)
 * @method static array getTenantDatabases()
 * @method static bool userHasAccessToTenant(\Illuminate\Database\Eloquent\Model $user, Tenant $tenant)
 * @method static void purgeConnections()
 * @method static string getConnectionNameForDatabase(TenantDatabase $database)
 */
class MultiTenancy extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Worldesports\MultiTenancy\MultiTenancy::class;
    }
}
