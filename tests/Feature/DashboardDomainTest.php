<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RobertBoes\Patchbay\ApplicationFactory;
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

    public function test_an_application_that_restricts_its_origins_still_admits_the_dashboard(): void
    {
        $application = app(ApplicationFactory::class)->make([
            'id' => '01HZY0000000000000000000AA',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'allowed_origins' => ['app.example.com'],
        ]);

        $this->assertSame(['app.example.com', 'dash.patchbay.test'], $application->allowedOrigins());
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
