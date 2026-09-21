<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\View\ComponentAttributeBag;
use Tests\TestCase;

/**
 * The mark has to survive having no front-end build behind it.
 *
 * This application ships no compiled Tailwind of its own — the panel is
 * served by Filament's stylesheet and the landing page by its own inline one.
 * A lockup sized with utility classes renders at whatever size the browser
 * feels like, which is how the logo first came out on top of the login form.
 */
class BrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Http::fake(['*/up' => Http::response('', 200)]);
    }

    public function test_the_login_page_carries_the_mark_and_the_name(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Patchbay')
            ->assertSee('viewBox="0 0 40 40"', escape: false);
    }

    public function test_the_mark_is_sized_without_relying_on_a_css_build(): void
    {
        $markup = (string) view('components.logo', ['attributes' => new ComponentAttributeBag([])])->render();

        $this->assertStringContainsString('width="40"', $markup);
        $this->assertStringContainsString('height="40"', $markup);
    }

    public function test_the_panel_and_the_landing_page_point_at_the_same_favicon(): void
    {
        $this->get('/')->assertSee('favicon.svg', escape: false);
        $this->get('/admin/login')->assertSee('favicon.svg', escape: false);

        $this->assertFileExists(public_path('favicon.svg'));
    }

    /**
     * Second factors are only a second factor if they are actually offered.
     */
    public function test_the_panel_offers_authenticator_app_codes_with_recovery(): void
    {
        $providers = filament()->getPanel('admin')->getMultiFactorAuthenticationProviders();

        $this->assertArrayHasKey('app', $providers);
    }

    public function test_the_profile_page_is_reachable_for_setting_one_up(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('filament.admin.auth.profile'))
            ->assertOk();
    }
}
