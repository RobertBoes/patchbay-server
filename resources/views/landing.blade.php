<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">

    <title>{{ config('dashboard.brand') }}</title>

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">

    @fonts

    {{--
        Deliberately not built by Vite. This page is the first thing a fresh
        deployment serves, and it must not depend on anyone having run a
        front-end build for the server to look like it is working.
    --}}
    <style>
        :root {
            --bg: #fbfbfa;
            --panel: #ffffff;
            --line: #e7e5e4;
            --ink: #1c1917;
            --muted: #78716c;
            --accent: #f59e0b;
            --ok: #059669;
            --warn: #d97706;
            --down: #dc2626;
            --patchbay-accent: var(--accent);
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0f0e0d;
                --panel: #1c1a18;
                --line: #2e2a26;
                --ink: #fafaf9;
                --muted: #a8a29e;
                --ok: #34d399;
                --warn: #fbbf24;
                --down: #f87171;
            }
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100dvh;
            display: grid;
            place-items: center;
            padding: 32px 16px;
            background: var(--bg);
            color: var(--ink);
            font-family: 'Instrument Sans', ui-sans-serif, system-ui, -apple-system, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        .card {
            width: 100%;
            max-width: 27rem;
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 40px 36px 32px;
            box-shadow: 0 1px 2px rgb(0 0 0 / 0.04), 0 12px 32px -12px rgb(0 0 0 / 0.10);
        }

        .lockup { display: flex; align-items: center; gap: 11px; }
        .lockup svg { width: 42px; height: 42px; color: var(--ink); }
        .lockup h1 { margin: 0; font-size: 26px; font-weight: 600; letter-spacing: -0.025em; }

        .tagline { margin: 18px 0 0; color: var(--muted); font-size: 15px; line-height: 1.55; }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            margin-top: 26px;
            padding: 7px 13px 7px 11px;
            border: 1px solid var(--line);
            border-radius: 999px;
            font-size: 13px;
            font-weight: 500;
        }

        .status .dot { width: 8px; height: 8px; border-radius: 50%; background: currentColor; }
        .status.operational { color: var(--ok); }
        .status.degraded { color: var(--warn); }
        .status.down { color: var(--down); }

        .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 30px; }

        a.button {
            flex: 1 1 auto;
            text-align: center;
            padding: 11px 18px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            border: 1px solid transparent;
            transition: opacity 120ms ease, background-color 120ms ease;
        }

        a.button:hover { opacity: 0.88; }
        a.button:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }

        a.primary { background: var(--ink); color: var(--bg); }
        a.secondary { border-color: var(--line); color: var(--ink); }

        @media (max-width: 380px) {
            .card { padding: 32px 24px 26px; }
        }
    </style>
</head>
<body>
    <main class="card">
        <div class="lockup">
            <x-logo width="42" height="42" />
            <h1>{{ config('dashboard.brand') }}</h1>
        </div>

        <p class="tagline">
            {{ config('dashboard.landing.tagline') ?? __('A Reverb control plane. WebSocket applications live in the database and can be changed while the server is running.') }}
        </p>

        <p class="status {{ $health->value }}"><span class="dot"></span>{{ $health->label() }}</p>

        <div class="actions">
            <a class="button primary" href="{{ route('filament.admin.pages.dashboard') }}">{{ __('Open dashboard') }}</a>

            @if ($docs = config('dashboard.landing.docs_url'))
                <a class="button secondary" href="{{ $docs }}" rel="noreferrer noopener">{{ __('Docs') }}</a>
            @endif
        </div>
    </main>
</body>
</html>
