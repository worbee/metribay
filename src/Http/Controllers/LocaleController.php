<?php

namespace Metribay\Core\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Metribay\Core\Http\Middleware\DemoMode;
use Metribay\Core\Services\Locales;

class LocaleController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $values = $request->validate(['locale' => ['required', 'string', Rule::in(array_keys(Locales::all()))]]);
        $request->session()->put(Locales::SESSION_KEY, $values['locale']);
        if (! DemoMode::enabled()) {
            $request->user()?->forceFill(['locale' => $values['locale']])->save();
        }
        app()->setLocale($values['locale']);

        return back()->with('status', __('Language updated.'));
    }
}
