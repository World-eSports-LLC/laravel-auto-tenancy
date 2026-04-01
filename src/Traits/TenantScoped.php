<?php

namespace Worldesports\MultiTenancy\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Worldesports\MultiTenancy\Facades\MultiTenancy;
use Worldesports\MultiTenancy\Models\Tenant;

/**
 * @used
 */

trait TenantScoped
{
    /**
     * Trait for models that need tenant-aware ID scoping
     * This is useful for models that have tenant_id column
     */
    public static function bootTenantScoped(): void
    {
        static::addGlobalScope('tenant_scoped', function (Builder $builder) {
            if (MultiTenancy::hasTenant()) {
                $builder->where('tenant_id', MultiTenancy::getTenantId());
            }
        });

        // Automatically set tenant_id on creation
        static::creating(function (Model $model) {
            if (MultiTenancy::hasTenant() && ! $model->tenant_id) {
                $model->tenant_id = MultiTenancy::getTenantId();
            }
        });
    }

    /**
     * Scope query to specific tenant by ID
     */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope query to current tenant
     */
    public function scopeForCurrentTenant(Builder $query): Builder
    {
        if (MultiTenancy::hasTenant()) {
            return $query->where('tenant_id', MultiTenancy::getTenantId());
        }

        return $query;
    }

    /**
     * Remove tenant scoping
     */
    public function scopeWithoutTenantScoping(Builder $query): Builder
    {
        return $query->withoutGlobalScope('tenant_scoped');
    }

    /**
     * Relationship to the tenant
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
