<?php

namespace Worldesports\MultiTenancy\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Worldesports\MultiTenancy\Facades\MultiTenancy;

trait BelongsToTenant
{
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

    public function scopeForTenant(Builder $query, ?int $tenantId = null): Builder
    {
        if ($tenantId) {
            // Switch to specific tenant connection temporarily
            $tenant = \Worldesports\MultiTenancy\Models\Tenant::find($tenantId);
            if ($tenant) {
                $database = $tenant->databases()->first();
                if ($database) {
                    $connectionName = MultiTenancy::setTenantDatabaseConnection($database);
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
