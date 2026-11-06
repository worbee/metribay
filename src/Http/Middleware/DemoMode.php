<?php

namespace Metribay\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public demo (docs/DEMO.md): visitors are signed in as the sample owner and every change is refused.
 * The host also opens the database read-only, so a request this misses still cannot write.
 */
class DemoMode
{
    public const NOTICE_KEY = 'metribay.demo_notice';

    /** Requests that change nothing stored: the language lives in the session, Livewire only re-renders. */
    private const ALLOWED = ['metribay.locale', '*livewire.update'];

    /** Safe requests that would leave the demo for a provider sign-in. */
    private const BLOCKED_READS = ['metribay.sources.google', 'metribay.sources.google.callback', 'metribay.google.callback', 'metribay.connections.google'];

    public static function enabled(): bool
    {
        return (bool) config('metribay.demo.enabled');
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! self::enabled()) {
            return $next($request);
        }
        if (! Auth::check()) {
            $model = config('auth.providers.users.model');
            $user = $model::query()->where('email', config('metribay.demo.email'))->first();
            if ($user) {
                Auth::login($user);
            }
        }
        $refused = $request->isMethodSafe() ? $request->routeIs(...self::BLOCKED_READS) : ! $request->routeIs(...self::ALLOWED);
        if ($refused) {
            $message = __('Not available in the demo.');
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 403);
            }

            return redirect()->to(url()->previous(route('metribay.home')))->with(self::NOTICE_KEY, $message);
        }

        return $next($request);
    }
}
