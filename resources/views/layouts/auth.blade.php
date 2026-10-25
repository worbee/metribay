<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>@include('metribay::partials/head')</head>
<body class="auth-body">
<a class="skip-link" href="#main">Skip to content</a>
<header class="auth-header">@include('metribay::partials/brand')<button class="icon-button theme-cycle" type="button" data-theme-cycle aria-label="Change color theme">@include('metribay::partials/icon', ['name' => 'sun'])</button></header>
<main id="main" class="auth-main" tabindex="-1">
    <div class="auth-kicker">{{ ($edition ?? config('metribay.edition')) === 'cloud' ? 'Metribay Cloud' : 'Metribay Personal' }}</div>
    <h1>@yield('heading')</h1>
    <p class="auth-description">@yield('description')</p>
    @include('metribay::partials/flash')
    @yield('content')
</main>
<footer class="auth-footer"><span>Your projects. A clearer picture.</span><a href="{{ $marketingUrl ?? config('metribay.marketing_url', 'https://metribay.com') }}">metribay.com</a></footer>
@include('metribay::partials/scripts')
</body>
</html>
