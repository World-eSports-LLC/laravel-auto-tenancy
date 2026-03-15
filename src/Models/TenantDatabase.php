<?php

namespace Worldesports\MultiTenancy\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property bool $is_primary
 * @property array $connection_details
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, TenantDatabaseMetadata> $metadata
 *
 * @mixin Builder
 */
class TenantDatabase extends Model
{
    protected $guarded = [];

    protected $casts = [
        'connection_details' => 'array',
        'is_primary' => 'boolean',
    ];

    protected $hidden = [
        'connection_details', // Hide sensitive connection details by default
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function metadata(): HasMany
    {
        return $this->hasMany(TenantDatabaseMetadata::class);
    }

    /**
     * Get the connection details with optional encryption
     */
    protected function connectionDetails(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (config('multi-tenancy.encrypt_connection_details', false) && $value) {
                    try {
                        $decoded = json_decode($value, true);

                        if (is_array($decoded) && array_key_exists('encrypted', $decoded)) {
                            return json_decode(decrypt($decoded['encrypted']), true);
                        }

                        if (is_array($decoded)) {
                            return $decoded;
                        }

                        if (is_string($decoded)) {
                            return json_decode(decrypt($decoded), true);
                        }

                        return json_decode(decrypt($value), true);
                    } catch (\Exception $e) {
                        // Fallback to unencrypted for backward compatibility
                        return json_decode($value, true);
                    }
                }

                return json_decode($value, true);
            },
            set: function ($value) {
                if (config('multi-tenancy.encrypt_connection_details', false)) {
                    return json_encode([
                        'encrypted' => encrypt(json_encode($value)),
                    ]);
                }

                return json_encode($value);
            }
        );
    }

    /**
     * Get sanitized connection details for display (without sensitive info)
     */
    public function getSafeConnectionDetailsAttribute(): array
    {
        $details = $this->connection_details;

        return [
            'driver' => $details['driver'] ?? null,
            'host' => $details['host'] ?? null,
            'port' => $details['port'] ?? null,
            'database' => $details['database'] ?? null,
            'charset' => $details['charset'] ?? null,
            'collation' => $details['collation'] ?? null,
            // Exclude password and username for security
        ];
    }

    /**
     * Check if the database connection is valid
     */
    public function testConnection(): bool
    {
        try {
            $details = $this->connection_details;
            $dsn = $this->buildDsn($details);
            $username = $details['username'] ?? '';
            $password = $details['password'] ?? '';
            $pdo = new \PDO($dsn, $username, $password);
            $pdo->query('SELECT 1');

            return true;
        } catch (\Exception $e) {
            \Log::error("Database connection test failed for {$this->name}: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Scope for active/healthy connections
     */
    public function scopeHealthy(Builder $query): Builder
    {
        // For now, just return all; add health check conditions here in future if needed.
        return $query;
    }

    private function buildDsn(array $details): string
    {
        $driver = $details['driver'] ?? null;
        if (! $driver) {
            throw new \InvalidArgumentException('Database driver is not specified.');
        }

        return match ($driver) {
            'sqlite' => $this->buildSqliteDsn($details),
            'sqlsrv' => $this->buildSqlsrvDsn($details),
            'pgsql' => $this->buildStandardDsn('pgsql', $details),
            'mysql' => $this->buildStandardDsn('mysql', $details),
            default => throw new \InvalidArgumentException("Unsupported database driver [{$driver}]."),
        };
    }

    private function buildSqliteDsn(array $details): string
    {
        $database = $details['database'] ?? null;
        if (! $database) {
            throw new \InvalidArgumentException('SQLite connection requires a database (path).');
        }

        return "sqlite:{$database}";
    }

    private function buildStandardDsn(string $driver, array $details): string
    {
        $host = $details['host'] ?? null;
        $database = $details['database'] ?? null;
        if (! $host || ! $database) {
            throw new \InvalidArgumentException("{$driver} connection requires both host and database.");
        }

        $port = $details['port'] ?? null;
        $dsn = "{$driver}:host={$host};dbname={$database}";
        if ($port) {
            $dsn .= ";port={$port}";
        }

        return $dsn;
    }

    private function buildSqlsrvDsn(array $details): string
    {
        $host = $details['host'] ?? null;
        $database = $details['database'] ?? null;
        if (! $host || ! $database) {
            throw new \InvalidArgumentException('sqlsrv connection requires both host and database.');
        }

        $port = $details['port'] ?? null;
        $server = $host;
        if ($port) {
            $server .= ',' . $port;
        }

        return "sqlsrv:Server={$server};Database={$database}";
    }
}
