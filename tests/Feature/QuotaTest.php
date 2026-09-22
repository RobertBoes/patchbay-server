<?php

namespace Tests\Feature;

use App\Models\App;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('dashboard.quotas', ['enabled' => true, 'apps' => 2, 'connections' => 10]);
        config()->set('patchbay.defaults.max_message_size', 10_000);
    }

    public function test_a_user_cannot_create_past_their_application_limit(): void
    {
        $user = User::factory()->create();
        App::factory()->for($user)->count(2)->create();

        $this->actingAs($user);

        $this->assertFalse($user->can('create', App::class));

        $this->expectException(AuthorizationException::class);
        App::create(['name' => 'one too many']);
    }

    public function test_the_create_page_turns_away_a_user_at_their_limit(): void
    {
        $user = User::factory()->create();
        App::factory()->for($user)->count(2)->create();

        $this->actingAs($user)->get('/admin/apps/create')->assertForbidden();
    }

    public function test_an_override_raises_a_users_limit(): void
    {
        $user = User::factory()->create(['app_limit' => 3]);
        App::factory()->for($user)->count(2)->create();

        $this->assertTrue($user->can('create', App::class));
    }

    public function test_admins_are_not_limited(): void
    {
        $admin = User::factory()->create();
        config()->set('dashboard.access.admins', [$admin->email]);
        App::factory()->for($admin)->count(2)->create();

        $this->assertTrue($admin->can('create', App::class));
        $this->assertNull($admin->connectionLimit());
    }

    public function test_nobody_is_limited_while_quotas_are_off(): void
    {
        config()->set('dashboard.quotas.enabled', false);

        $user = User::factory()->create();
        App::factory()->for($user)->count(2)->create();

        $this->assertTrue($user->can('create', App::class));
        $this->assertNull($user->connectionLimit());
    }

    public function test_connections_and_message_size_are_capped_to_the_quota(): void
    {
        $app = App::factory()->for(User::factory())->create([
            'max_connections' => 500,
            'max_message_size' => 50_000_000,
        ]);

        $this->assertSame(10, $app->max_connections);
        $this->assertSame(10_000, $app->max_message_size);

        $app->update(['max_connections' => 999]);

        $this->assertSame(10, $app->fresh()->max_connections);
    }

    public function test_an_unset_connection_limit_becomes_the_quota(): void
    {
        // Null means unlimited to Reverb.
        $app = App::factory()->for(User::factory())->create(['max_connections' => null]);

        $this->assertSame(10, $app->max_connections);
    }

    public function test_a_lower_limit_is_kept(): void
    {
        $app = App::factory()->for(User::factory())->create(['max_connections' => 3]);

        $this->assertSame(3, $app->max_connections);
    }

    public function test_applications_without_an_owner_are_not_capped(): void
    {
        $app = App::factory()->create(['max_connections' => 500]);

        $this->assertSame(500, $app->max_connections);
    }
}
