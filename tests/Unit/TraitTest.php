<?php

namespace Worldesports\MultiTenancy\Tests\Unit;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Worldesports\MultiTenancy\Facades\MultiTenancy;
use Worldesports\MultiTenancy\Models\Tenant;
use Worldesports\MultiTenancy\Models\TenantDatabase;
use Worldesports\MultiTenancy\Tests\Concerns\UsesTestMigrations;
use Worldesports\MultiTenancy\Tests\TestCase;
use Worldesports\MultiTenancy\Tests\TestUser;
use Worldesports\MultiTenancy\Traits\BelongsToTenant;
use Worldesports\MultiTenancy\Traits\TenantScoped;

class TraitTest extends TestCase
{
    use UsesTestMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = TestUser::factory()->create();

        $this->tenant = Tenant::create([
            'user_id' => $this->user->id,
            'name' => 'Test Tenant',
        ]);

        $this->tenantDatabase = TenantDatabase::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'test_db',
            'connection_details' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
            'is_primary' => true,
        ]);
    }

    /** @test */
    public function test_belongs_to_tenant_trait_sets_connection()
    {
        $model = new class extends Model
        {
            use BelongsToTenant;

            protected $table = 'test_models';

            protected $fillable = ['name'];
        };

        // Debug: Check if tenant and database exist
        $this->assertNotNull($this->tenant);
        $this->assertNotNull($this->tenantDatabase);
        $this->assertEquals(1, $this->tenant->databases()->count());

        // Debug: Check primary database
        $primaryDb = $this->tenant->primaryDatabase();
        $this->assertNotNull($primaryDb, 'Primary database should not be null');
        $this->assertEquals($this->tenantDatabase->id, $primaryDb->id);

        MultiTenancy::setTenant($this->tenant);

        $connectionName = $model->getConnectionName();

        $this->assertNotNull($connectionName);
        $this->assertStringContainsString('tenant_connection_', $connectionName);
    }

    /** @test */
    public function test_tenant_scoped_trait_adds_tenant_id()
    {
        $model = new class extends Model
        {
            use TenantScoped;

            protected $table = 'test_scoped_models';

            protected $fillable = ['name', 'tenant_id'];
        };

        MultiTenancy::setTenant($this->tenant);

        $connectionName = MultiTenancy::getCurrentConnectionName();
        Schema::connection($connectionName)->create('test_scoped_models', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();
        });

        // Simulate model creation
        $instance = $model->newInstance(['name' => 'Test']);
        $instance->save();

        // The creating event should set tenant_id
        $this->assertEquals($this->tenant->id, $instance->tenant_id);
    }

    /** @test */
    public function test_model_can_bypass_tenant_scoping()
    {
        $model = new class extends Model
        {
            use BelongsToTenant;

            protected $table = 'test_models';
        };

        MultiTenancy::setTenant($this->tenant);

        $query = $model::withoutTenantScope();

        $this->assertInstanceOf(Builder::class, $query);
    }
}
