@extends('metribay::layouts/auth')
@section('title', __('Choose a new password'))
@section('heading', __('A fresh start.'))
@section('description', __('Choose a strong password for your Metribay account.'))
@section('content')
<form class="form-stack" method="POST" action="{{ route('password.update') }}">@csrf
    <input type="hidden" name="token" value="{{ $token ?? request()->route('token') }}">
    <div class="field"><label for="email">{{ __('Email address') }}</label><input id="email" name="email" type="email" value="{{ old('email', $email ?? request('email')) }}" autocomplete="username" required></div>
    <div class="field"><label for="password">{{ __('New password') }}</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="14" required autofocus><p class="field-hint">{{ __('At least 14 characters.') }}</p></div>
    <div class="field"><label for="password_confirmation">{{ __('Confirm new password') }}</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="14" required></div>
    <button class="button button-primary button-full" type="submit">{{ __('Reset password') }}</button>
</form>
@endsection
