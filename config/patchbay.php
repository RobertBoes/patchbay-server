<?php

use App\Models\App;
use App\Models\Metric;
use RobertBoes\Patchbay\Reload\CacheReloadDriver;
use RobertBoes\Patchbay\Reload\NullReloadDriver;
use RobertBoes\Patchbay\Sources\EloquentAppSource;

return [

    /*
    |--------------------------------------------------------------------------
    | Application Source
    |--------------------------------------------------------------------------
    |
    | Where Patchbay loads your Reverb applications from. The default loads
    | them from the database with Eloquent, which is the source of truth.
    | Point this at your own implementation of the AppSource contract to
    | load them from somewhere else, such as an internal HTTP API.
    |
    */

    'source' => EloquentAppSource::class,

    'model' => App::class,

    'table' => 'patchbay_apps',

    'connection' => env('PATCHBAY_DB_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Reload Driver
    |--------------------------------------------------------------------------
    |
    | How the running Reverb server learns that an application changed. The
    | server keeps applications in memory, so it needs to be told when to
    | refresh. The "cache" driver needs no extra infrastructure at all.
    |
    | Supported: "cache", "null"
    |
    */

    'reload' => [

        'driver' => env('PATCHBAY_RELOAD_DRIVER', 'cache'),

        'drivers' => [

            'cache' => [
                'via' => CacheReloadDriver::class,

                // Cache store used to pass change signals to the server.
                // Must be shared between the control panel and the server,
                // so "array" and "file" will not work across processes.
                'store' => env('PATCHBAY_CACHE_STORE'),

                // How often the server checks for changes, in seconds. Each
                // check is a single cache read; applications are only
                // re-read when the version actually moved.
                'interval' => env('PATCHBAY_RELOAD_INTERVAL', 5),

                // How many recent changes to keep. If the server falls
                // further behind than this it does a full reload instead
                // of replaying, so a dropped signal can never desync it.
                'backlog' => 100,
            ],

            'null' => [
                'via' => NullReloadDriver::class,
            ],

        ],

        // Safety net. Every so often the server reloads everything from the
        // repository regardless of signals, so a missed change cannot go
        // unnoticed forever. Set to null to disable.
        'reconcile_every' => env('PATCHBAY_RECONCILE_INTERVAL', 300),

    ],

    /*
    |--------------------------------------------------------------------------
    | Application Defaults
    |--------------------------------------------------------------------------
    |
    | Applied to every application that does not override them. These mirror
    | the per-app options Reverb supports, so anything you can set in the
    | reverb config you can set per application here.
    |
    */

    'defaults' => [

        'ping_interval' => env('PATCHBAY_PING_INTERVAL', 60),

        'activity_timeout' => env('PATCHBAY_ACTIVITY_TIMEOUT', 30),

        'max_message_size' => env('PATCHBAY_MAX_MESSAGE_SIZE', 10_000),

        'max_connections' => env('PATCHBAY_MAX_CONNECTIONS'),

        'accept_client_events_from' => env('PATCHBAY_ACCEPT_CLIENT_EVENTS_FROM', 'members'),

        'allowed_origins' => ['*'],

        'rate_limiting' => [
            'enabled' => env('PATCHBAY_RATE_LIMITING_ENABLED', false),
            'max_attempts' => env('PATCHBAY_RATE_LIMIT_MAX_ATTEMPTS', 60),
            'decay_seconds' => env('PATCHBAY_RATE_LIMIT_DECAY_SECONDS', 60),
            'terminate_on_limit' => env('PATCHBAY_RATE_LIMIT_TERMINATE', false),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Connection Options
    |--------------------------------------------------------------------------
    |
    | Handed to clients as the address they should connect to. These are the
    | values that end up in an application's .env snippet, so they describe
    | how the outside world reaches your server, not how it binds.
    |
    */

    'options' => [
        'host' => env('PATCHBAY_HOST', env('REVERB_HOST')),
        'port' => env('PATCHBAY_PORT', env('REVERB_PORT', 443)),
        'scheme' => env('PATCHBAY_SCHEME', env('REVERB_SCHEME', 'https')),
        'useTLS' => env('PATCHBAY_SCHEME', env('REVERB_SCHEME', 'https')) === 'https',
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    |
    | Samples what each application is doing, recorded from inside the running
    | server. Connection and channel counts are already in memory there, and
    | message throughput exists nowhere else — the HTTP API does not report it.
    |
    | Messages are counted in memory and written once per interval, so the
    | database never sits on the path of an individual frame.
    |
    */

    'metrics' => [

        'enabled' => env('PATCHBAY_METRICS_ENABLED', true),

        'model' => Metric::class,

        'table' => 'patchbay_metrics',

        // Which server a sample came from. Every Reverb server records on its
        // own timer, so a fleet reading is the latest sample from each of
        // them. Defaults to the machine's hostname, which is enough unless
        // you run more than one server per host.
        'server' => env('PATCHBAY_SERVER_NAME'),

        // How often a sample is written, in seconds. Shorter gives a finer
        // graph and more rows; an application recorded every minute produces
        // about 43,000 rows a month.
        'interval' => env('PATCHBAY_METRICS_INTERVAL', 60),

        // Samples older than this are removed by `patchbay:prune-metrics`.
        // Set to null to keep them forever.
        'retain_days' => env('PATCHBAY_METRICS_RETAIN_DAYS', 7),

    ],

];
