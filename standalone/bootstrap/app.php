<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Metribay\Core\Http\Middleware\SecurityHeaders;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Password-reset and verification links must never inherit an arbitrary request host.
        $middleware->trustHosts(at: fn (): array => ['^'.preg_quote((string) parse_url(config('app.url'), PHP_URL_HOST), '/').'$'], subdomains: false);
        $middleware->appendToGroup('web', SecurityHeaders::class);
        // Comma-separated proxy addresses or CIDR ranges, for HTTPS terminated by a reverse proxy.
        if ($proxies = array_filter(explode(',', (string) env('METRIBAY_TRUSTED_PROXIES', '')))) {
            $middleware->trustProxies(at: $proxies);
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['values', 'token', 'password', 'password_confirmation', 'current_password']);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
