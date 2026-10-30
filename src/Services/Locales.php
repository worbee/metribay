<?php

namespace Metribay\Core\Services;

use Illuminate\Http\Request;

final class Locales
{
    public const SESSION_KEY = 'metribay_locale';

    /** @return array<string, string> Locale code => native language name. */
    public static function all(): array
    {
        return config('metribay.locales', ['en' => 'English']);
    }

    public static function supported(mixed $locale): bool
    {
        return is_string($locale) && array_key_exists($locale, self::all());
    }

    public static function forRequest(Request $request): string
    {
        $user = $request->user();
        if ($user && self::supported($user->locale ?? null)) {
            return $user->locale;
        }
        if ($request->hasSession() && self::supported($request->session()->get(self::SESSION_KEY))) {
            return $request->session()->get(self::SESSION_KEY);
        }
        $fallback = self::supported(config('app.locale')) ? config('app.locale') : array_key_first(self::all());

        return $request->headers->has('Accept-Language') ? ($request->getPreferredLanguage(array_keys(self::all())) ?? $fallback) : $fallback;
    }
}
