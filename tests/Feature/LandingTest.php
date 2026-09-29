<?php

namespace Tests\Feature;

use App\Models\App as ReverbApp;
use App\Models\Metric;
use App\Models\User;
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

    protected function sample(ReverbApp $app, int $connections, int $messages, $at, string $server = 'ws-1'): void
    {
        Metric::create([
            'app_id' => $app->id,
            'server' => $server,
            'connections' => $connections,
            'messages_sent' => $messages,
            'messages_received' => 0,
            'recorded_at' => $at,
        ]);
    }

    public function test_traffic_is_not_published_unless_asked_for(): void
    {
        $this->get('/')->assertOk()->assertDontSee('messages in the last day');
    }

    public function test_it_publishes_fleet_totals_when_asked(): void
    {
        config()->set('dashboard.landing.metrics', true);

        $mine = ReverbApp::factory()->for(User::factory())->create(['name' => 'customer-portal']);
        $theirs = ReverbApp::factory()->for(User::factory())->create();

        $this->sample($mine, 4, 100, now()->subHours(3));
        $this->sample($mine, 3, 20, now());
        $this->sample($theirs, 5, 30, now());

        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder(['8', 'connections right now'])
            ->assertSeeInOrder(['150', 'messages in the last day'])
            ->assertDontSee('customer-portal');
    }

    public function test_a_signed_in_visitor_sees_the_same_totals_as_anyone(): void
    {
        config()->set('dashboard.landing.metrics', true);

        $visitor = User::factory()->create();
        $this->sample(ReverbApp::factory()->for($visitor)->create(), 1, 0, now());
        $this->sample(ReverbApp::factory()->for(User::factory())->create(), 6, 0, now());

        $this->actingAs($visitor)
            ->get('/')
            ->assertSeeInOrder(['7', 'connections right now']);
    }

    public function test_a_server_that_stopped_reporting_holds_no_connections_now(): void
    {
        config()->set('dashboard.landing.metrics', true);

        $this->sample(ReverbApp::factory()->create(), 40, 0, now()->subMinutes(30));

        $this->get('/')->assertSeeInOrder(['0', 'connections right now']);
    }
}
