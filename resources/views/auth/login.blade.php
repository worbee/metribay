@extends('metribay::layouts/auth')
@section('title', __('Sign in'))
@section('heading', __('Welcome back.'))
@section('description', __('Sign in to see how your projects are doing.'))
@section('content')
<form class="form-stack" method="POST" action="{{ route('login') }}">@csrf
    <div class="field"><label for="email">{{ __('Email address') }}</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username webauthn" required autofocus></div>
    <div class="field"><div class="field-label-row"><label for="password">{{ __('Password') }}</label><a class="text-link" href="{{ route('password.request') }}">{{ __('Forgot password?') }}</a></div><input id="password" name="password" type="password" autocomplete="current-password" required></div>
    <label class="checkbox-label"><input type="checkbox" name="remember" value="1" @checked(old('remember'))> {{ __('Keep me signed in') }}</label>
    <button class="button button-primary button-full" type="submit">{{ __('Sign in') }} @include('metribay::partials/icon', ['name'=>'arrow-up-right'])</button>
</form>
@if (\Illuminate\Support\Facades\Route::has('passkey.login'))
<div class="passkey-alternative" data-passkey data-options-url="{{ route('passkey.login-options') }}" data-submit-url="{{ route('passkey.login') }}" hidden>
    <p class="auth-or"><span>{{ __('or') }}</span></p>
    <button class="button button-secondary button-full" type="button" data-passkey-start>@include('metribay::partials/icon', ['name'=>'key']) {{ __('Sign in with a passkey') }}</button>
    <p class="passkey-error" role="alert" data-passkey-error hidden></p>
</div>
@endif
@if (\Illuminate\Support\Facades\Route::has('register'))
<p class="auth-alternative">{{ __('New to Metribay?') }} <a class="text-link" href="{{ route('register') }}">{{ __('Create an account') }}</a></p>
@endif
@endsection
