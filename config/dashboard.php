<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dashboard Domain
    |--------------------------------------------------------------------------
    |
    | The hostname the control panel answers on. Set it to give the dashboard
    | a name of its own — dash.example.com — while the WebSocket server keeps
    | ws.example.com. The panel then refuses to serve on any other host, so a
    | request that reaches this application by the WebSocket name gets a 404
    | rather than a login form.
    |
    | Left empty the panel answers on every hostname, which is what a local
    | install wants and what a single-domain deployment can keep.
    |
    */

    'domain' => env('DASHBOARD_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Branding
    |--------------------------------------------------------------------------
    |
    | The name shown beside the mark, in the browser tab, and in an
    | authenticator app once someone sets up two-factor authentication.
    |
    */

    'brand' => env('DASHBOARD_BRAND', 'Patchbay'),

    /*
    |--------------------------------------------------------------------------
    | Landing Page
    |--------------------------------------------------------------------------
    |
    | What the root of the dashboard domain serves. Enabled, it is a page
    | naming the service and linking to the panel; disabled, the root is a
    | 404 and the deployment gives nothing away to anyone who has not been
    | told where to look.
    |
    | The page reports whether the WebSocket server is answering, because
    | that is the one question a public status page exists to answer. It
    | reports nothing else — no application names, no counts, no addresses.
    |
    */

    'landing' => [

        'enabled' => (bool) env('DASHBOARD_LANDING', true),

        // Shown under the name. Null falls back to a generic description.
        'tagline' => env('DASHBOARD_LANDING_TAGLINE'),

        // Optional "Docs" link. Omitted from the page when empty.
        'docs_url' => env('DASHBOARD_DOCS_URL', 'https://github.com/RobertBoes/patchbay'),

    ],

    /*
    |--------------------------------------------------------------------------
    | Panel Access
    |--------------------------------------------------------------------------
    |
    | Who may sign in, over and above holding valid credentials. There is no
    | registration route — accounts are made with `php artisan make:filament-user`
    | — so an empty allowlist still means "the people already in the users
    | table", not "anyone".
    |
    | Filling it in adds a second gate that a stray database row cannot pass:
    | a comma-separated list of the addresses allowed to reach the panel.
    |
    */

    'access' => [

        'emails' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('DASHBOARD_ALLOWED_EMAILS', '')),
        ))),

    ],

    /*
    |--------------------------------------------------------------------------
    | Two-Factor Authentication
    |--------------------------------------------------------------------------
    |
    | Authenticator-app codes are always available from the profile page.
    | Required, nobody reaches the panel until they have set them up — they
    | are sent to the setup screen straight after signing in.
    |
    */

    'require_mfa' => env('DASHBOARD_REQUIRE_MFA', false),

];
