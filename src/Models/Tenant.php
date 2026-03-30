<?php

namespace Worldesports\MultiTenancy\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $domain
 * @property string|null $subdomain
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Model $user
 * @property-read Collection<int, TenantDatabase> $databases
 *
 * @mixin Builder
 */
class Tenant extends Model
{
    protected $table = 'tenants';

    protected $fillable = [
        'user_id',
        'name',
        'domain',
        'subdomain',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('multi-tenancy.user_model', 'App\\Models\\User'));
    }

    /**
     * Relationship: One user can have many tenants.
     * This supports a 1:many architecture where a single authenticated user
     * can own or manage multiple organizations/workspaces.
     * If you need to restrict to 1 tenant per user, enforce that at the application level.
     */
    public function databases(): HasMany
    {
        return $this->hasMany(TenantDatabase::class);
    }

    /**
     * Get the primary database for this tenant
     */
    public function primaryDatabase(): ?TenantDatabase
    {
        // Ensure relationship is loaded only once
        $this->loadMissing('databases');

        /** @var TenantDatabase|null $primary */
        $primary = $this->databases->firstWhere('is_primary', true);

        if ($primary) {
            return $primary;
        }

        /** @var TenantDatabase|null $first */
        return $this->databases->first();
    }

    /**
     * Scope for finding tenant by domain
     */
    public function scopeByDomain(Builder $query, string $domain): Builder
    {
        return $query->where('domain', $domain);
    }

    /**
     * Scope for finding tenant by subdomain
     */
    public function scopeBySubdomain(Builder $query, string $subdomain): Builder
    {
        return $query->where('subdomain', $subdomain);
    }
}
