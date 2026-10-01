<?php

namespace Tests\Feature;

use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Tests\TestCase;

/**
 * Laravel skips TrustHosts under test, so this applies its patterns directly.
 */
class TrustedHostsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://patchbay.test', 'dashboard.domain' => 'dash.patchbay.test']);
        Request::setTrustedHosts(array_filter(app(TrustHosts::class)->hosts()));
    }

    protected function tearDown(): void
    {
        Request::setTrustedHosts([]);

        parent::tearDown();
    }

    public function test_the_app_url_the_dashboard_domain_and_the_healthcheck_are_trusted(): void
    {
        foreach (['patchbay.test', 'dash.patchbay.test', 'localhost'] as $host) {
            $this->assertSame($host, Request::create("http://{$host}/up")->getHost());
        }
    }

    public function test_any_other_host_is_refused(): void
    {
        foreach (['evil.test', 'localhost.evil.test', 'dash.patchbay.test.evil.test'] as $host) {
            try {
                Request::create("http://{$host}/up")->getHost();
                $this->fail("{$host} was trusted.");
            } catch (SuspiciousOperationException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
