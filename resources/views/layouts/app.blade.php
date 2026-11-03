<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>@include('metribay::partials/head')</head>
<body>
<a class="skip-link" href="#main">{{ __('Skip to content') }}</a>
<header class="app-header">
    <div class="app-header-inner">
        @include('metribay::partials/brand', ['brandHref' => $navigationWorkspace ? route('metribay.dashboard', $navigationWorkspace->slug) : route('metribay.home')])
        @includeIf('metribay::partials/workspace-switcher')
        <div class="header-right">
            <button class="icon-button theme-cycle" type="button" data-theme-cycle aria-label="{{ __('Change color theme') }}" title="{{ __('Change color theme') }}">@include('metribay::partials/icon', ['name' => 'sun'])</button>
            <details class="account-menu">
                <summary class="user-avatar" aria-label="{{ config('metribay.account_enabled', true) ? __('Profile menu') : __('Main navigation') }}">@if(config('metribay.account_enabled', true)){{ mb_strtoupper(mb_substr(auth()->user()?->name ?? 'M', 0, 1)) }}@else @include('metribay::partials/icon', ['name' => 'overview']) @endif</summary>
                <div class="menu-popover">
                    @if(config('metribay.account_enabled', true))<div class="menu-identity"><strong>{{ auth()->user()?->name }}</strong><span>{{ auth()->user()?->email }}</span></div>@endif
                    <nav class="menu-navigation" aria-label="{{ __('Main navigation') }}">
                        @if($navigationWorkspace)
                        <div class="menu-section">
                            @if(\Illuminate\Support\Facades\Route::has('metribay.workspaces'))<p class="menu-section-label">{{ $navigationWorkspace->name }}</p>@endif
                            <a href="{{ route('metribay.dashboard', $navigationWorkspace->slug) }}" @if(request()->routeIs('metribay.dashboard', 'metribay.projects.show', 'metribay.projects.create')) aria-current="page" @endif>@include('metribay::partials/icon', ['name' => 'overview']) {{ __('Overview') }}</a>
                            <a href="{{ route('metribay.settings', $navigationWorkspace->slug) }}" @if(request()->routeIs('metribay.settings', 'metribay.projects.edit', 'metribay.sources.create')) aria-current="page" @endif>@include('metribay::partials/icon', ['name' => 'settings']) {{ __('Settings') }}</a>
                            @foreach(config('metribay.navigation', []) as $item)
                            @if(!empty($item['workspace']) && \Illuminate\Support\Facades\Route::has($item['route']))<a href="{{ route($item['route'], $navigationWorkspace->slug) }}" @if(request()->routeIs($item['route'])) aria-current="page" @endif>@include('metribay::partials/icon', ['name' => $item['icon'] ?? 'layers']) {{ __($item['label']) }}</a>@endif
                            @endforeach
                        </div>
                        @endif
                        <div class="menu-section">
                            @if(\Illuminate\Support\Facades\Route::has('metribay.workspaces'))<a href="{{ route('metribay.workspaces') }}" @if(request()->routeIs('metribay.workspaces', 'cloud.workspaces.*')) aria-current="page" @endif>@include('metribay::partials/icon', ['name' => 'layers']) {{ __('Workspaces') }}</a>@endif
                            @if(config('metribay.account_enabled', true))<a href="{{ route('metribay.profile') }}" @if(request()->routeIs('metribay.profile')) aria-current="page" @endif>@include('metribay::partials/icon', ['name' => 'user']) {{ __('Profile & preferences') }}</a>@endif
                            @foreach(config('metribay.navigation', []) as $item)
                            @if(empty($item['workspace']) && \Illuminate\Support\Facades\Route::has($item['route']))<a href="{{ route($item['route']) }}" @if(request()->routeIs($item['route'])) aria-current="page" @endif>@include('metribay::partials/icon', ['name' => $item['icon'] ?? 'layers']) {{ __($item['label']) }}</a>@endif
                            @endforeach
                        </div>
                    </nav>
                    @if(config('metribay.account_enabled', true))<form class="menu-signout" method="POST" action="{{ route('logout') }}">@csrf<button type="submit">@include('metribay::partials/icon', ['name' => 'logout']) {{ __('Sign out') }}</button></form>@endif
                </div>
            </details>
        </div>
    </div>
</header>
<main id="main" class="app-main" tabindex="-1">
    @include('metribay::partials/flash')
    @hasSection('content') @yield('content') @else {{ $slot ?? '' }} @endif
</main>
<footer class="app-footer"><span>Metribay · {{ ($edition ?? config('metribay.edition')) === 'cloud' ? 'Cloud' : __('Personal edition') }}</span><span>{{ __('Missing data stays missing.') }}</span><a href="{{ $marketingUrl ?? config('metribay.marketing_url', 'https://metribay.com') }}">metribay.com @include('metribay::partials/icon', ['name' => 'arrow-up-right'])</a>@include('metribay::partials/privacy-link')</footer>
@include('metribay::partials/scripts')
</body>
</html>
