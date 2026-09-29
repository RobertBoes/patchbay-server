<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\EditProfile;
use App\Models\App;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_delete_their_account_and_its_applications(): void
    {
        $user = User::factory()->create();
        App::factory()->for($user)->count(2)->create();

        Livewire::actingAs($user)
            ->test(EditProfile::class)
            ->callAction('deleteAccount', data: ['currentPassword' => 'password'])
            ->assertHasNoActionErrors()
            ->assertRedirect(filament()->getLoginUrl());

        $this->assertModelMissing($user);
        $this->assertSame(0, App::withoutGlobalScopes()->count());
        $this->assertGuest();
    }

    public function test_the_wrong_password_deletes_nothing(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(EditProfile::class)
            ->callAction('deleteAccount', data: ['currentPassword' => 'not-my-password'])
            ->assertHasActionErrors(['currentPassword']);

        $this->assertModelExists($user);
    }

    public function test_admins_are_not_offered_account_deletion(): void
    {
        $admin = User::factory()->create();
        config()->set('dashboard.access.admins', [$admin->email]);

        $this->actingAs($admin)
            ->get('/admin/profile')
            ->assertOk()
            ->assertDontSee('Delete account');
    }
}
