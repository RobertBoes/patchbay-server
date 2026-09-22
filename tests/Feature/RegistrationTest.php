<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Auth\Notifications\VerifyEmail;
use Filament\Auth\Pages\Register;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        // Read when the panel is built, before any test body runs.
        $_SERVER['DASHBOARD_REGISTRATION'] = 'true';

        parent::setUp();
    }

    protected function tearDown(): void
    {
        unset($_SERVER['DASHBOARD_REGISTRATION']);

        parent::tearDown();
    }

    public function test_signing_up_sends_a_verification_email(): void
    {
        Notification::fake();

        Livewire::test(Register::class)
            ->fillForm([
                'name' => 'Newcomer',
                'email' => 'new@example.test',
                'password' => 'correct horse battery',
                'passwordConfirmation' => 'correct horse battery',
            ])
            ->call('register')
            ->assertHasNoFormErrors();

        $user = User::firstWhere('email', 'new@example.test');

        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
    }

    public function test_signing_up_succeeds_with_real_mail(): void
    {
        // Unfaked, so the verification mail and its link are really built.
        Livewire::test(Register::class)
            ->fillForm([
                'name' => 'Newcomer',
                'email' => 'new@example.test',
                'password' => 'correct horse battery',
                'passwordConfirmation' => 'correct horse battery',
            ])
            ->call('register')
            ->assertHasNoFormErrors();

        $this->assertAuthenticated();
    }

    public function test_an_unverified_account_is_held_at_the_prompt(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get('/admin')
            ->assertRedirect('/admin/email-verification/prompt');
    }

    public function test_an_admin_is_never_held_at_the_prompt(): void
    {
        $admin = User::factory()->unverified()->create();
        config()->set('dashboard.access.admins', [$admin->email]);

        $this->actingAs($admin)->get('/admin')->assertOk();
    }
}
