@extends('metribay::layouts/auth')
@section('title', __('Create account'))
@section('heading', __('Your projects, together.'))
@section('description', __('Create your account and connect your first project.'))
@section('content')
<form class="form-stack" method="POST" action="{{ route('register') }}">@csrf
    <div class="field"><label for="name">{{ __('Your name') }}</label><input id="name" name="name" value="{{ old('name') }}" autocomplete="name" maxlength="120" required autofocus></div>
    <div class="field"><label for="email">{{ __('Email address') }}</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required></div>
    <div class="field"><label for="password">{{ __('Password') }}</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="14" required><p class="field-hint">{{ __('At least 14 characters.') }}</p></div>
    <div class="field"><label for="password_confirmation">{{ __('Confirm password') }}</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="14" required></div>
    <button class="button button-primary button-full" type="submit">{{ __('Create account') }} @include('metribay::partials/icon', ['name'=>'arrow-up-right'])</button>
</form>
<p class="auth-alternative">{{ __('Already have an account?') }} <a class="text-link" href="{{ route('login') }}">{{ __('Sign in') }}</a></p>
@endsection
