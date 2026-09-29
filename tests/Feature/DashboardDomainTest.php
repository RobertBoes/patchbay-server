<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Splitting dash.example.com from ws.example.com is only worth anything if the
 * WebSocket hostname really does stop serving the dashboard.
 */
class DashboardDomainTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $_SERVER['DASHBOARD_DOMAIN'] = 'dash.patchbay.test';

        parent::setUp();

        Http::preventStrayRequests();
        Http::fake(['*/up' => Http::response('', 200)]);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['DASHBOARD_DOMAIN']);

        parent::tearDown();
    }

    public function test_the_dashboard_host_serves_the_panel_and_the_landing_page(): void
    {
        $this->get('http://dash.patchbay.test/')->assertOk();
        $this->get('http://dash.patchbay.test/admin/login')->assertOk();
    }

    public function test_every_other_host_serves_neither(): void
    {
        $this->get('http://ws.patchbay.test/')->assertNotFound();
        $this->get('http://ws.patchbay.test/admin/login')->assertNotFound();
    }

    public function test_generated_links_carry_the_dashboard_host(): void
    {
        $this->assertStringContainsString(
            'dash.patchbay.test',
            route('filament.admin.pages.dashboard'),
        );
    }
}
