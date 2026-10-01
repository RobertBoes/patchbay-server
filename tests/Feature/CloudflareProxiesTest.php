<?php

namespace Tests\Feature;

use RobertBoes\CloudflareProxies\Testing\InteractsWithCloudflareProxies;
use Tests\TestCase;

/**
 * The login throttle buckets by client address, so a visitor must not be able
 * to pick theirs with a made-up X-Forwarded-For entry.
 */
class CloudflareProxiesTest extends TestCase
{
    use InteractsWithCloudflareProxies;

    public function test_the_client_is_resolved_behind_cloudflare(): void
    {
        $this->expectCloudflareProxiesToWork();
    }
}
