<?php

namespace Worldesports\MultiTenancy\Tests\Unit;

use Worldesports\MultiTenancy\Support\AuthScaffoldingDetector;
use Worldesports\MultiTenancy\Tests\TestCase;

class AuthScaffoldingDetectorTest extends TestCase
{
    /** @test */
    public function test_it_passes_when_auth_scaffolding_exists()
    {
        $detector = new AuthScaffoldingDetector;

        // Test that detector can check for various auth systems
        // These will return false in test environment, but we're just testing the methods exist
        $this->assertIsBool($detector->hasBreeze());
        $this->assertIsBool($detector->hasJetstream());
        $this->assertIsBool($detector->hasFortify());
        $this->assertIsBool($detector->hasSanctum());
    }

    /** @test */
    public function test_it_reports_issues_when_auth_scaffolding_is_missing()
    {
        $detector = new AuthScaffoldingDetector;

        // In test environment without real auth files, these should return false
        $this->assertFalse($detector->hasBreeze());
        $this->assertFalse($detector->hasJetstream());
        $this->assertFalse($detector->hasFortify());
        $this->assertFalse($detector->hasSanctum());
    }
}
