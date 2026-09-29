<?php

namespace Tests\Feature;

use App\Models\App;
use App\Models\Metric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use RobertBoes\Patchbay\Contracts\AppSource;
use RobertBoes\Patchbay\Filament\Resources\AppResource\Pages\ListApps;
use Tests\TestCase;

class AppOwnershipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Http::fake(['*' => Http::response('', 200)]);
    }

    public function test_an_application_belongs_to_whoever_made_it(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $app = App::create(['name' => 'mine']);

        $this->assertTrue($app->user->is($user));
    }

    public function test_a_user_lists_only_their_own_applications(): void
    {
        $user = User::factory()->create();
        $mine = App::factory()->for($user)->create();
        $theirs = App::factory()->for(User::factory())->create();
        $nobodys = App::factory()->create();

        $this->actingAs($user);

        Livewire::test(ListApps::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs, $nobodys]);
    }

    public function test_another_users_application_is_not_found(): void
    {
        $theirs = App::factory()->for(User::factory())->create();

        $this->actingAs(User::factory()->create())
            ->get("/admin/apps/{$theirs->id}")
            ->assertNotFound();
    }

    public function test_an_admin_sees_every_application(): void
    {
        $admin = User::factory()->create();
        config()->set('dashboard.access.admins', [$admin->email]);

        App::factory()->for(User::factory())->create();
        App::factory()->create();

        $this->actingAs($admin);

        $this->assertSame(2, App::count());
    }

    public function test_the_server_loads_every_application(): void
    {
        App::factory()->for(User::factory())->create();
        App::factory()->for(User::factory())->create();
        App::factory()->create();

        // No one is signed in inside the Reverb process. If ownership leaked
        // in here, applications would stop accepting connections.
        $this->assertCount(3, app(AppSource::class)->load());
    }

    public function test_a_user_sees_only_their_own_metrics(): void
    {
        $user = User::factory()->create();
        $mine = App::factory()->for($user)->create();
        $theirs = App::factory()->for(User::factory())->create();

        foreach ([$mine, $theirs] as $app) {
            Metric::create(['app_id' => $app->id, 'connections' => 1, 'recorded_at' => now()]);
        }

        $this->actingAs($user);

        $this->assertSame([$mine->id], Metric::pluck('app_id')->all());
    }
}
