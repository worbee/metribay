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
        app()->setLocale(Locales::forRequest($request));

        return $next($request);
    }
}
