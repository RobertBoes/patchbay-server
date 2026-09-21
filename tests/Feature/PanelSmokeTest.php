<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use RobertBoes\Patchbay\Filament\Resources\AppResource\Pages\ViewApp;
use RobertBoes\Patchbay\Models\App as ReverbApp;
use Tests\TestCase;

/**
 * Renders every Patchbay panel page against a real panel, so a Filament API
 * that has moved fails here rather than in someone's browser.
 */
class PanelSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        // The panel reads live metrics from the running server. Tests must
        // not reach out to whatever happens to be listening on this machine.
        Http::preventStrayRequests();
        Http::fake([
            '*/up' => Http::response('', 200),
            '*/channels*' => Http::response(['channels' => ['orders' => []]]),
            '*/connections*' => Http::response(['connections' => 4]),
        ]);
    }

    public function test_the_application_list_renders(): void
    {
        ReverbApp::factory()->count(3)->create();

        $this->get('/admin/apps')->assertOk();
    }

    public function test_the_create_form_renders(): void
    {
        $this->get('/admin/apps/create')->assertOk();
    }

    public function test_the_view_page_renders(): void
    {
        $app = ReverbApp::create(['name' => 'viewable']);

        $this->get("/admin/apps/{$app->id}")
            ->assertOk()
            ->assertSee($app->key)
            ->assertDontSee($app->secret);
    }

    public function test_the_edit_form_renders(): void
    {
        $app = ReverbApp::create(['name' => 'editable']);

        $this->get("/admin/apps/{$app->id}/edit")->assertOk();
    }

    public function test_the_secret_is_hidden_by_default(): void
    {
        $app = ReverbApp::create(['name' => 'secretive']);

        $this->get("/admin/apps/{$app->id}")->assertDontSee($app->secret);
    }

    public function test_revealing_toggles_in_one_click(): void
    {
        $app = ReverbApp::create(['name' => 'secretive']);

        Livewire::test(ViewApp::class, ['record' => $app->id])
            ->assertDontSee($app->secret)
            ->callAction('reveal')
            ->assertSet('secretRevealed', true)
            ->assertSee($app->secret)
            ->callAction('reveal')
            ->assertSet('secretRevealed', false)
            ->assertDontSee($app->secret);
    }

    public function test_the_env_block_appears_once_revealed(): void
    {
        $app = ReverbApp::create(['name' => 'enveloped']);

        Livewire::test(ViewApp::class, ['record' => $app->id])
            ->callAction('reveal')
            ->assertSee('REVERB_APP_ID='.$app->id)
            ->assertSee('VITE_REVERB_APP_KEY', escape: false);
    }

    /**
     * Creating an application hands the secret over exactly once. It must not
     * still be on screen the next time the page is opened.
     */
    public function test_the_handover_from_creation_is_consumed(): void
    {
        $app = ReverbApp::create(['name' => 'fresh']);

        $this->withSession(['patchbay.reveal' => $app->id])
            ->get("/admin/apps/{$app->id}")
            ->assertSee($app->secret);

        $this->get("/admin/apps/{$app->id}")->assertDontSee($app->secret);
    }

    /**
     * Filament caches the action objects, so a label computed while the action
     * is built describes the record as it was before the click.
     */
    public function test_deactivating_updates_the_button_without_a_reload(): void
    {
        $app = ReverbApp::create(['name' => 'toggleable']);

        Livewire::test(ViewApp::class, ['record' => $app->id])
            ->assertActionHasLabel('activation', 'Deactivate')
            ->assertActionHasColor('activation', 'warning')
            ->callAction('activation')
            ->assertActionHasLabel('activation', 'Activate')
            ->assertActionHasColor('activation', 'success');

        $this->assertFalse($app->fresh()->active);
    }

    public function test_activating_updates_the_button_without_a_reload(): void
    {
        $app = ReverbApp::create(['name' => 'inactive', 'active' => false]);

        Livewire::test(ViewApp::class, ['record' => $app->id])
            ->assertActionHasLabel('activation', 'Activate')
            ->callAction('activation')
            ->assertActionHasLabel('activation', 'Deactivate');

        $this->assertTrue($app->fresh()->active);
    }

    public function test_revealing_updates_the_button_without_a_reload(): void
    {
        $app = ReverbApp::create(['name' => 'revealable']);

        Livewire::test(ViewApp::class, ['record' => $app->id])
            ->assertActionHasLabel('reveal', 'Reveal secret')
            ->callAction('reveal')
            ->assertActionHasLabel('reveal', 'Hide secret')
            ->callAction('reveal')
            ->assertActionHasLabel('reveal', 'Reveal secret');
    }

    public function test_live_figures_are_shown_for_an_active_application(): void
    {
        $app = ReverbApp::create(['name' => 'busy']);

        $this->get("/admin/apps/{$app->id}")
            ->assertSee('4')
            ->assertSee('orders');
    }

    /**
     * A deactivated application is working as intended, so it must not read
     * as though the server had fallen over.
     */
    public function test_a_deactivated_application_does_not_report_the_server_as_down(): void
    {
        $app = ReverbApp::create(['name' => 'paused', 'active' => false]);

        $this->get("/admin/apps/{$app->id}")
            ->assertSee('Inactive')
            ->assertDontSee('Server unreachable');
    }

    public function test_an_unreachable_server_is_named_as_such(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('refused'));

        $app = ReverbApp::create(['name' => 'orphaned']);

        $this->get("/admin/apps/{$app->id}")->assertSee('Server unreachable');
    }

    public function test_rotating_the_secret_reveals_the_new_one(): void
    {
        $app = ReverbApp::create(['name' => 'rotatable']);
        $original = $app->secret;

        Livewire::test(ViewApp::class, ['record' => $app->id])
            ->callAction('rotateSecret')
            ->assertSet('secretRevealed', true);

        $this->assertNotSame($original, $app->fresh()->secret);
    }
}
