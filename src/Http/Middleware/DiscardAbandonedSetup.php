<?php

namespace Metribay\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Metribay\Core\Http\Controllers\SourceSetupController;

/**
 * Data source setup is never resumed: opening any other page discards the unfinished form state.
 * Setup pages and provider sign-in round trips (listed by hosts in config) keep it.
 */
class DiscardAbandonedSetup
{
    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->isMethod('GET') && $request->hasSession() && $request->user() && ! $request->routeIs(...$this->setupRoutes())) {
            $request->session()->forget(SourceSetupController::SESSION_KEY);
        }

        return $next($request);
    }

    /** @return list<string> */
    private function setupRoutes(): array
    {
        return array_values(array_merge(['metribay.sources.*', 'metribay.google.callback'], config('metribay.source_setup.routes', [])));
    }
}
