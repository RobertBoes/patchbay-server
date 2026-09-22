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
    | that is the one question a public status page exists to answer. Unless
    | metrics are turned on it reports nothing else, and even then only
    | fleet-wide totals: no application names, no counts, no addresses.
    |
    */

    'landing' => [

        'enabled' => (bool) env('DASHBOARD_LANDING', true),

        // Shown under the name. Null falls back to a generic description.
        'tagline' => env('DASHBOARD_LANDING_TAGLINE'),

        // Optional "Docs" link. Omitted from the page when empty.
        'docs_url' => env('DASHBOARD_DOCS_URL', 'https://github.com/RobertBoes/patchbay'),

        // Fleet-wide traffic: connections right now, messages over the last
        // day, and a chart of it. Totals only, never an application's name
        // or share, but still more than a private deployment may want to
        // publish, so it is off unless asked for.
        'metrics' => (bool) env('DASHBOARD_LANDING_METRICS', false),

    ],

    /*
    |--------------------------------------------------------------------------
    | Panel Access
    |--------------------------------------------------------------------------
    |
    | Who may sign in, over and above holding valid credentials. Accounts are
    | made with `php artisan make:filament-user`, or by signing up when
    | registration is open.
    |
    | Filling in the allowlist adds a second gate that a stray database row
    | cannot pass: a comma-separated list of the addresses allowed to reach
    | the panel. It applies to people who sign up too, so an open dashboard
    | leaves it empty.
    |
    */

    'access' => [

        'emails' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('DASHBOARD_ALLOWED_EMAILS', '')),
        ))),

        // Everyone else sees only the applications they made. Admins see
        // all of them, including those made with `patchbay:app`, which
        // belong to no one.
        'admins' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('DASHBOARD_ADMINS', '')),
        ))),

    ],

    /*
    |--------------------------------------------------------------------------
    | Registration
    |--------------------------------------------------------------------------
    |
    | Whether anyone may sign up. Open, new accounts confirm their address
    | before they reach the panel; admins never have to, so turning this on
    | cannot lock out an operator whose account was made from the CLI.
    |
    | The confirmation email is queued, so run a queue worker, or sign-ups
    | wait at the prompt forever. Pair it with quotas: an open dashboard with
    | none hands every stranger an unlimited Reverb application.
    |
    */

    'registration' => (bool) env('DASHBOARD_REGISTRATION', false),

    /*
    |--------------------------------------------------------------------------
    | Quotas
    |--------------------------------------------------------------------------
    |
    | Limits for everyone who is not an admin, for a dashboard open to people
    | you do not know. Off, every account is unlimited, which is what a
    | deployment for yourself or your team wants.
    |
    | The connection limit applies to each application, not to the account as
    | a whole: Reverb enforces it per application, so no bookkeeping is needed
    | to make it stick. A user's `app_limit` and `connection_limit` columns
    | override these when set.
    |
    */

    'quotas' => [

        'enabled' => (bool) env('DASHBOARD_QUOTAS', false),

        'apps' => (int) env('DASHBOARD_QUOTA_APPS', 10),

        'connections' => (int) env('DASHBOARD_QUOTA_CONNECTIONS', 10),

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
