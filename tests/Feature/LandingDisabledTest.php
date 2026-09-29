<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A deployment that would rather not advertise itself turns the page off, and
 * the root has to become a genuine 404 — not a page saying it is switched off.
 */
class LandingDisabledTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        // Set before the application boots, because that is when the config
        // file this drives is read.
        $_SERVER['DASHBOARD_LANDING'] = 'false';

        parent::setUp();
    }

    protected function tearDown(): void
    {
        unset($_SERVER['DASHBOARD_LANDING']);

        parent::tearDown();
    }

    public function test_the_root_is_not_found(): void
    {
        $this->get('/')->assertNotFound();
    }
}
