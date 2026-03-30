<?php

namespace Worldesports\MultiTenancy\Tests;

use Worldesports\MultiTenancy\MultiTenancy;
use Worldesports\MultiTenancy\Models\Tenant;
use Worldesports\MultiTenancy\Models\TenantDatabase;
use Worldesports\MultiTenancy\Models\TenantDatabaseMetadata;

class MultiTenancyTest extends TestCase
{
    public function test_can_instantiate_the_multi_tenancy_class(): void
    {
        $multiTenancy = new MultiTenancy;
        $this->assertInstanceOf(MultiTenancy::class, $multiTenancy);
    }

    public function test_can_echo_a_phrase(): void
    {
        $multiTenancy = new MultiTenancy;
        $result = $multiTenancy->echoPhrase('Hello, World!');
        $this->assertSame('Hello, World!', $result);
    }

    public function test_returns_false_for_has_tenant_when_no_tenant_is_set(): void
    {
        $multiTenancy = new MultiTenancy;
        $this->assertFalse($multiTenancy->hasTenant());
    }

    public function test_returns_null_for_get_tenant_when_no_tenant_is_set(): void
    {
        $multiTenancy = new MultiTenancy;
        $this->assertNull($multiTenancy->getTenant());
    }

    public function test_returns_null_for_get_tenant_id_when_no_tenant_is_set(): void
    {
        $multiTenancy = new MultiTenancy;
        $this->assertNull($multiTenancy->getTenantId());
    }

    public function test_returns_empty_array_for_get_tenant_databases_when_no_tenant_is_set(): void
    {
        $multiTenancy = new MultiTenancy;
        $this->assertSame([], $multiTenancy->getTenantDatabases());
    }

    public function test_can_reset_tenant_context(): void
    {
        $multiTenancy = new MultiTenancy;
        $multiTenancy->resetTenant();
        $this->assertFalse($multiTenancy->hasTenant());
    }

    public function test_models_use_explicit_fillable_attributes(): void
    {
        $this->assertSame(['user_id', 'name', 'domain', 'subdomain'], (new Tenant)->getFillable());
        $this->assertSame(['tenant_id', 'name', 'connection_details', 'is_primary'], (new TenantDatabase)->getFillable());
        $this->assertSame(['tenant_database_id', 'key', 'value'], (new TenantDatabaseMetadata)->getFillable());
    }
}
