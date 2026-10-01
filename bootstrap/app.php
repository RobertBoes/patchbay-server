<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Proxies are trusted by robertboes/laravel-cloudflare-proxies. Laravel
        // adds APP_URL's host and subdomains to these; DASHBOARD_DOMAIN may
        // differ from it, and the image's healthcheck curls localhost.
        $middleware->trustHosts(at: fn (): array => array_filter([
            '^localhost$',
            config('dashboard.domain') ? '^'.preg_quote(config('dashboard.domain')).'$' : null,
        ]));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
