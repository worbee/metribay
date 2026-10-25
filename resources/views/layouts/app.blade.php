<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>@include('metribay::partials/head')</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="app-header">
    <div class="app-header-inner">
        @include('metribay::partials/brand', ['brandHref' => isset($workspace) && $workspace ? route('metribay.dashboard', $workspace->slug) : route('metribay.workspaces')])
        <span class="header-divider" aria-hidden="true">/</span>
        <a class="workspace-switch" href="{{ route('metribay.workspaces') }}" aria-label="Change workspace">
            <span class="workspace-avatar">{{ mb_strtoupper(mb_substr($workspace->name ?? 'M', 0, 1)) }}</span>
            <span class="workspace-name">{{ $workspace->name ?? 'Workspaces' }}</span>
            @include('metribay::partials/icon', ['name' => 'chevrons'])
        </a>
        <div class="header-right">
            <span class="edition-label">{{ ($edition ?? config('metribay.edition')) === 'cloud' ? 'Cloud' : 'Personal' }}</span>
            <button class="icon-button theme-cycle" type="button" data-theme-cycle aria-label="Change color theme" title="Change color theme">@include('metribay::partials/icon', ['name' => 'sun'])</button>
            <details class="account-menu">
                <summary class="user-avatar" aria-label="Account menu">{{ mb_strtoupper(mb_substr(auth()->user()?->name ?? 'M', 0, 1)) }}</summary>
                <div class="menu-popover">
                    <div class="menu-identity"><strong>{{ auth()->user()?->name }}</strong><span>{{ auth()->user()?->email }}</span></div>
                    <a href="{{ route('metribay.account') }}">@include('metribay::partials/icon', ['name' => 'user']) Account & preferences</a>
                    <a href="{{ route('metribay.workspaces') }}">@include('metribay::partials/icon', ['name' => 'layers']) Workspaces</a>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">@include('metribay::partials/icon', ['name' => 'logout']) Sign out</button></form>
                </div>
            </details>
        </div>
    </div>
    <div class="app-nav-wrap"><nav class="app-nav" aria-label="Main navigation">
        @if (isset($workspace) && $workspace)
        <a @class(['nav-item', 'is-active' => request()->routeIs('metribay.dashboard', 'metribay.projects.show')]) href="{{ route('metribay.dashboard', $workspace->slug) }}" @if(request()->routeIs('metribay.dashboard', 'metribay.projects.show')) aria-current="page" @endif>@include('metribay::partials/icon', ['name' => 'overview']) Overview</a>
        <a @class(['nav-item', 'is-active' => request()->routeIs('metribay.settings')]) href="{{ route('metribay.settings', $workspace->slug) }}" @if(request()->routeIs('metribay.settings')) aria-current="page" @endif>@include('metribay::partials/icon', ['name' => 'settings']) Settings</a>
        @foreach (config('metribay.navigation', []) as $item)
            @if (\Illuminate\Support\Facades\Route::has($item['route']) && (empty($item['workspace']) || $workspace))
                <a @class(['nav-item', 'is-active' => request()->routeIs($item['route'])]) href="{{ !empty($item['workspace']) ? route($item['route'], $workspace->slug) : route($item['route']) }}">{{ $item['label'] }}</a>
            @endif
        @endforeach
        @else
        <a @class(['nav-item', 'is-active' => request()->routeIs('metribay.workspaces')]) href="{{ route('metribay.workspaces') }}">@include('metribay::partials/icon', ['name' => 'layers']) Workspaces</a>
        <a @class(['nav-item', 'is-active' => request()->routeIs('metribay.account')]) href="{{ route('metribay.account') }}">@include('metribay::partials/icon', ['name' => 'user']) Account</a>
        @foreach (config('metribay.navigation', []) as $item)
            @if (empty($item['workspace']) && \Illuminate\Support\Facades\Route::has($item['route']))
                <a @class(['nav-item', 'is-active' => request()->routeIs($item['route'])]) href="{{ route($item['route']) }}" @if(request()->routeIs($item['route'])) aria-current="page" @endif>{{ $item['label'] }}</a>
            @endif
        @endforeach
        @endif
    </nav></div>
</header>
<main id="main" class="app-main" tabindex="-1">
    @include('metribay::partials/flash')
    @hasSection('content') @yield('content') @else {{ $slot ?? '' }} @endif
</main>
<footer class="app-footer"><span>Metribay · {{ ($edition ?? config('metribay.edition')) === 'cloud' ? 'Cloud' : 'Personal edition' }}</span><span>Missing data stays missing.</span><a href="{{ $marketingUrl ?? config('metribay.marketing_url', 'https://metribay.com') }}">metribay.com @include('metribay::partials/icon', ['name' => 'arrow-up-right'])</a></footer>
@include('metribay::partials/scripts')
</body>
</html>
