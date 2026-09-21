<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Who reaches the panel, and what the second factor is stored as.
 */
class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_there_is_no_registration_route(): void
    {
        $this->get('/admin/register')->assertNotFound();
    }

    public function test_the_panel_is_closed_to_anyone_not_signed_in(): void
    {
        $this->get('/admin')->assertRedirect(route('filament.admin.auth.login'));
    }

    /**
     * With no allowlist configured, the users table is the allowlist. An
     * account exists because someone made it on the server.
     */
    public function test_any_account_may_sign_in_when_no_allowlist_is_set(): void
    {
        config(['dashboard.access.emails' => []]);

        $this->assertTrue(
            User::factory()->create()->canAccessPanel(filament()->getPanel('admin')),
        );
    }

    public function test_an_allowlist_keeps_everyone_else_out(): void
    {
        config(['dashboard.access.emails' => ['ops@example.com']]);

        $panel = filament()->getPanel('admin');

        $this->assertTrue(User::factory()->create(['email' => 'ops@example.com'])->canAccessPanel($panel));
        $this->assertFalse(User::factory()->create(['email' => 'someone@example.com'])->canAccessPanel($panel));
    }

    /**
     * Addresses are not case sensitive, and an allowlist that says otherwise
     * locks out the person who typed their own address in capitals.
     */
    public function test_the_allowlist_ignores_case(): void
    {
        config(['dashboard.access.emails' => ['Ops@Example.com']]);

        $this->assertTrue(
            User::factory()->create(['email' => 'ops@example.com'])
                ->canAccessPanel(filament()->getPanel('admin')),
        );
    }

    public function test_an_allowlisted_user_reaching_the_panel_is_let_through(): void
    {
        config(['dashboard.access.emails' => ['ops@example.com']]);

        $this->actingAs(User::factory()->create(['email' => 'ops@example.com']))
            ->get('/admin')
            ->assertOk();
    }

    public function test_a_user_who_has_been_taken_off_the_allowlist_is_turned_away(): void
    {
        config(['dashboard.access.emails' => ['ops@example.com']]);

        $this->actingAs(User::factory()->create(['email' => 'former@example.com']))
            ->get('/admin')
            ->assertForbidden();
    }

    /**
     * A TOTP secret mints valid codes for as long as it exists, so a database
     * dump must not be a working set of second factors.
     */
    public function test_the_two_factor_secret_is_not_stored_in_the_clear(): void
    {
        $user = User::factory()->create();

        $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

        $this->assertSame('JBSWY3DPEHPK3PXP', $user->fresh()->getAppAuthenticationSecret());

        $stored = DB::table('users')->where('id', $user->id)->value('app_authentication_secret');

        $this->assertNotSame('JBSWY3DPEHPK3PXP', $stored);
        $this->assertStringNotContainsString('JBSWY3DPEHPK3PXP', (string) $stored);
    }

    public function test_recovery_codes_are_not_stored_in_the_clear(): void
    {
        $user = User::factory()->create();

        $user->saveAppAuthenticationRecoveryCodes(['first-code', 'second-code']);

        $this->assertSame(['first-code', 'second-code'], $user->fresh()->getAppAuthenticationRecoveryCodes());

        $stored = DB::table('users')->where('id', $user->id)->value('app_authentication_recovery_codes');

        $this->assertStringNotContainsString('first-code', (string) $stored);
    }
}
