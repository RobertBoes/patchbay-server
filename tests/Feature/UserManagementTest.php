<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\App;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Http::fake(['*' => Http::response('', 200)]);

        $this->admin = User::factory()->create();
        config()->set('dashboard.access.admins', [$this->admin->email]);
    }

    public function test_only_admins_reach_the_user_list(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/users')->assertForbidden();

        $this->actingAs($this->admin)->get('/admin/users')->assertOk();
    }

    public function test_an_admin_can_raise_one_users_quota(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($this->admin)
            ->test(ManageUsers::class)
            ->callAction(TestAction::make('edit')->table($user), data: [
                'app_limit' => 25,
                'connection_limit' => 50,
            ])
            ->assertHasNoFormErrors();

        $this->assertSame(25, $user->fresh()->app_limit);
        $this->assertSame(50, $user->fresh()->connection_limit);
    }

    public function test_disabling_a_user_locks_them_out_and_takes_their_apps_offline(): void
    {
        $user = User::factory()->create();
        $app = App::factory()->for($user)->create();

        Livewire::actingAs($this->admin)
            ->test(ManageUsers::class)
            ->callAction(TestAction::make('disable')->table($user));

        $this->assertTrue($user->fresh()->isDisabled());
        $this->assertFalse($app->fresh()->active);

        $this->actingAs($user->fresh())->get('/admin')->assertForbidden();
    }

    public function test_an_admin_cannot_be_disabled_or_deleted_from_the_list(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ManageUsers::class)
            ->assertActionHidden(TestAction::make('disable')->table($this->admin))
            ->assertActionHidden(TestAction::make('delete')->table($this->admin));
    }

    public function test_deleting_a_user_deletes_their_apps_through_the_model(): void
    {
        $user = User::factory()->create();
        App::factory()->for($user)->count(2)->create();

        // Model events are what tell the running server; the foreign key's
        // cascade would remove the rows silently.
        $deleted = 0;
        App::deleted(function () use (&$deleted) {
            $deleted++;
        });

        $user->delete();

        $this->assertSame(2, $deleted);
        $this->assertSame(0, App::withoutGlobalScopes()->count());
    }
}
