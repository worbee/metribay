<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>@include('metribay::partials/head')</head>
<body>
<a class="skip-link" href="#main">{{ __('Skip to content') }}</a>
<header class="app-header">
    <div class="app-header-inner">
        @include('metribay::partials/brand', ['brandHref' => isset($workspace) && $workspace ? route('metribay.dashboard', $workspace->slug) : route('metribay.home')])
        @includeIf('metribay::partials/workspace-switcher')
        <div class="header-right">
            <span class="edition-label">{{ ($edition ?? config('metribay.edition')) === 'cloud' ? 'Cloud' : __('Personal') }}</span>
            <button class="icon-button theme-cycle" type="button" data-theme-cycle aria-label="{{ __('Change color theme') }}" title="{{ __('Change color theme') }}">@include('metribay::partials/icon', ['name' => 'sun'])</button>
            @if(config('metribay.account_enabled', true))
            <details class="account-menu">
                <summary class="user-avatar" aria-label="{{ __('Profile menu') }}">{{ mb_strtoupper(mb_substr(auth()->user()?->name ?? 'M', 0, 1)) }}</summary>
                <div class="menu-popover">
                    <div class="menu-identity"><strong>{{ auth()->user()?->name }}</strong><span>{{ auth()->user()?->email }}</span></div>
                    <a href="{{ route('metribay.profile') }}">@include('metribay::partials/icon', ['name' => 'user']) {{ __('Profile & preferences') }}</a>
                    @if(\Illuminate\Support\Facades\Route::has('metribay.workspaces'))
                    <a href="{{ route('metribay.workspaces') }}">@include('metribay::partials/icon', ['name' => 'layers']) {{ __('Workspaces') }}</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">@include('metribay::partials/icon', ['name' => 'logout']) {{ __('Sign out') }}</button></form>
                </div>
            </details>
            @endif
        </div>
    </div>
    <div class="app-nav-wrap"><nav class="app-nav" aria-label="{{ __('Main navigation') }}">
        @if (isset($workspace) && $workspace)
        <a @class(['nav-item', 'is-active' => request()->routeIs('metribay.dashboard', 'metribay.projects.show', 'metribay.projects.create')]) href="{{ route('metribay.dashboard', $workspace->slug) }}" @if(request()->routeIs('metribay.dashboard', 'metribay.projects.show', 'metribay.projects.create')) aria-current="page" @endif>@include('metribay::partials/icon', ['name' => 'overview']) {{ __('Overview') }}</a>
        <a @class(['nav-item', 'is-active' => request()->routeIs('metribay.settings', 'metribay.projects.edit', 'metribay.sources.create')]) href="{{ route('metribay.settings', $workspace->slug) }}" @if(request()->routeIs('metribay.settings')) aria-current="page" @endif>@include('metribay::partials/icon', ['name' => 'settings']) {{ __('Settings') }}</a>
        @foreach (config('metribay.navigation', []) as $item)
            @if (\Illuminate\Support\Facades\Route::has($item['route']) && (empty($item['workspace']) || $workspace))
                <a @class(['nav-item', 'is-active' => request()->routeIs($item['route'])]) href="{{ !empty($item['workspace']) ? route($item['route'], $workspace->slug) : route($item['route']) }}">{{ __($item['label']) }}</a>
            @endif
        @endforeach
        @else
        @if(\Illuminate\Support\Facades\Route::has('metribay.workspaces'))
        <a @class(['nav-item', 'is-active' => request()->routeIs('metribay.workspaces')]) href="{{ route('metribay.workspaces') }}">@include('metribay::partials/icon', ['name' => 'layers']) {{ __('Workspaces') }}</a>
        @else
        <a class="nav-item" href="{{ route('metribay.home') }}">@include('metribay::partials/icon', ['name' => 'overview']) {{ __('Overview') }}</a>
        @endif
        @if(config('metribay.account_enabled', true))
        <a @class(['nav-item', 'is-active' => request()->routeIs('metribay.profile')]) href="{{ route('metribay.profile') }}">@include('metribay::partials/icon', ['name' => 'user']) {{ __('Profile') }}</a>
        @endif
        @foreach (config('metribay.navigation', []) as $item)
            @if (empty($item['workspace']) && \Illuminate\Support\Facades\Route::has($item['route']))
                <a @class(['nav-item', 'is-active' => request()->routeIs($item['route'])]) href="{{ route($item['route']) }}" @if(request()->routeIs($item['route'])) aria-current="page" @endif>{{ __($item['label']) }}</a>
            @endif
        @endforeach
        @endif
    </nav></div>
</header>
<main id="main" class="app-main" tabindex="-1">
    @include('metribay::partials/flash')
    @hasSection('content') @yield('content') @else {{ $slot ?? '' }} @endif
</main>
<footer class="app-footer"><span>Metribay · {{ ($edition ?? config('metribay.edition')) === 'cloud' ? 'Cloud' : __('Personal edition') }}</span><span>{{ __('Missing data stays missing.') }}</span><a href="{{ $marketingUrl ?? config('metribay.marketing_url', 'https://metribay.com') }}">metribay.com @include('metribay::partials/icon', ['name' => 'arrow-up-right'])</a></footer>
@include('metribay::partials/scripts')
</body>
</html>
