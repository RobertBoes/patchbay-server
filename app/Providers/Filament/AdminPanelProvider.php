<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\EditProfile;
use App\Models\App;
use App\Models\User;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use RobertBoes\Patchbay\Filament\PatchbayPlugin;
use RobertBoes\Patchbay\Filament\Resources\AppResource\Pages\ListApps;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->domain(config('dashboard.domain'))
            ->login()
            ->when(
                config('dashboard.registration'),
                fn (Panel $panel): Panel => $panel->registration()->emailVerification(),
            )
            ->profile(EditProfile::class, isSimple: false)
            ->multiFactorAuthentication(
                AppAuthentication::make()
                    ->recoverable()
                    ->brandName(config('dashboard.brand')),
                isRequired: (bool) config('dashboard.require_mfa'),
            )

            ->brandName(config('dashboard.brand'))
            ->brandLogo(fn () => view('components.brand'))
            ->brandLogoHeight('1.75rem')
            ->favicon(asset('favicon.svg'))
            ->colors([
                'primary' => Color::Amber,
                'gray' => Color::Stone,
            ])
            ->font('Instrument Sans')

            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugin(
                PatchbayPlugin::make()
                    ->widgets()
                    // The owner's limit when editing, since that is what the
                    // save is held to; the signed-in user's when creating.
                    ->connectionLimit(fn (?App $application): ?int => ($application?->user ?? User::current())?->connectionLimit()),
            )
            ->renderHook(
                PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE,
                fn (): string => view('components.quota', ['user' => User::current()])->render(),
                scopes: ListApps::class,
            )
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
