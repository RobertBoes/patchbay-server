<?php

namespace Tests\Feature;

use App\Models\App as ReverbApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RobertBoes\Patchbay\Server\Heartbeat;
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

    public function test_it_reports_a_server_serving_its_applications_as_operational(): void
    {
        ReverbApp::create(['name' => 'serving']);
        app(Heartbeat::class)->beat(1);

        $this->get('/')->assertOk()->assertSee('Operational');
    }

    public function test_it_reports_a_server_serving_nothing_as_degraded(): void
    {
        // The failure the HTTP check alone cannot see: answering, holding no
        // applications, refusing every connection.
        ReverbApp::create(['name' => 'stranded']);
        app(Heartbeat::class)->beat(0);

        $this->get('/')->assertOk()->assertSee('Degraded');
    }

    public function test_it_reports_an_unreachable_server_rather_than_pretending(): void
    {
        Http::fake(fn () => throw new ConnectionException('refused'));

        $this->get('/')->assertOk()->assertSee('Unreachable');
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
