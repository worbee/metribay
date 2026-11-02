<?php

namespace Metribay\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Metribay\Core\Http\Controllers\ProjectFaviconController;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $formActions = ["'self'"];
        foreach (config('metribay.security.form_action_hosts', []) as $host) {
            if (is_string($host) && preg_match('~^https://[a-z0-9.-]+$~D', $host)) {
                $formActions[] = $host;
            }
        }
        $response->headers->set('Content-Security-Policy', "frame-ancestors 'none'; base-uri 'self'; form-action ".implode(' ', $formActions)."; object-src 'none'");

        if ($request->routeIs('metribay.projects.favicon')) {
            $response->headers->set('Content-Security-Policy', ProjectFaviconController::CONTENT_SECURITY_POLICY);
        }

        return $response;
    }
}
