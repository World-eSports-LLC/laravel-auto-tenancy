<?php

namespace Worldesports\MultiTenancy\Tests\Concerns;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Worldesports\MultiTenancy\Tests\TestCase;

trait UsesTestMigrations
{
    use RefreshDatabase {
        migrateFreshUsing as baseMigrateFreshUsing;
    }

    protected function migrateFreshUsing()
    {
        $options = $this->baseMigrateFreshUsing();
        $options['--path'] = TestCase::migrationsPath();
        $options['--realpath'] = true;

        return $options;
    }
}
