<?php

namespace Metribay\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Metribay\Core\Services\Locales;

/** Uses the signed-in user's language, then the guest's choice, then the browser's preference. */
class SetLocale
{
    public function handle(Request $request, Closure $next): mixed
    {
        // A guest arriving with ?lang= (such as from the marketing site) chooses that language for the session.
        $requested = $request->query('lang');
        if (! $request->user() && $request->hasSession() && Locales::supported($requested)) {
            $request->session()->put(Locales::SESSION_KEY, $requested);
        }
        app()->setLocale(Locales::forRequest($request));

        return $next($request);
    }
}
