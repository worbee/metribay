<?php

namespace Metribay\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Metribay\Core\Http\Controllers\SourceSetupController;

/**
 * Data source setup is never resumed: opening any other page discards the unfinished form state.
 * Setup pages and provider sign-in round trips (listed by hosts in config) keep it. So do requests the person
 * did not open: browser prefetches and prerenders, and fetches for something other than a page.
 */
class DiscardAbandonedSetup
{
    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->isMethod('GET') && $request->hasSession() && $request->user() && $this->opensPage($request) && ! $request->routeIs(...$this->setupRoutes())) {
            $request->session()->forget(SourceSetupController::SESSION_KEY);
        }

        return $next($request);
    }

    /** A page the person opened. Browsers that send no fetch metadata count as opening one. */
    private function opensPage(Request $request): bool
    {
        foreach (['Sec-Purpose', 'Purpose', 'X-Purpose', 'X-Moz'] as $header) {
            $purpose = strtolower((string) $request->headers->get($header, ''));
            if (str_contains($purpose, 'prefetch') || str_contains($purpose, 'prerender') || str_contains($purpose, 'preview')) {
                return false;
            }
        }
        $mode = $request->headers->get('Sec-Fetch-Mode');
        $destination = $request->headers->get('Sec-Fetch-Dest');

        return ($mode === null || $mode === 'navigate') && ($destination === null || $destination === 'document');
    }

    /** @return list<string> */
    private function setupRoutes(): array
    {
        return array_values(array_merge(['metribay.sources.*', 'metribay.google.callback'], config('metribay.source_setup.routes', [])));
    }
}
