<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RobertBoes\Patchbay\Models\App as ReverbApp;
use Tests\TestCase;

/**
 * The one page anyone can reach without signing in.
 */
class LandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Http::fake(['*/up' => Http::response('', 200)]);
    }

    public function test_it_names_the_service_and_links_to_the_panel(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Patchbay')
            ->assertSee(route('filament.admin.pages.dashboard'));
    }

    public function test_it_reports_a_reachable_server_as_operational(): void
    {
        $this->get('/')->assertOk()->assertSee('Operational');
    }

    public function test_it_reports_an_unreachable_server_rather_than_pretending(): void
    {
        Http::fake(fn () => throw new ConnectionException('refused'));

        $this->get('/')->assertOk()->assertSee('Server unreachable');
    }

    /**
     * Application names and counts are operational detail. A page that needs
     * no credentials is the wrong place to publish them, so nothing about
     * what this server is carrying may appear here.
     */
    public function test_it_gives_nothing_away_about_the_applications(): void
    {
        ReverbApp::create(['name' => 'customer-portal']);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('customer-portal');
    }

    public function test_it_asks_not_to_be_indexed(): void
    {
        $this->get('/')->assertSee('noindex', escape: false);
    }
}
