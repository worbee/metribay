@extends('metribay::layouts/auth')
@section('title', __('Confirm password'))
@section('heading', __('Confirm it’s you.'))
@section('description', __('Enter your password to continue with this change.'))
@section('content')
<form class="form-stack" method="POST" action="{{ route('password.confirm') }}">@csrf
    <div class="field"><label for="password">{{ __('Password') }}</label><input id="password" name="password" type="password" autocomplete="current-password" required autofocus></div>
    <button class="button button-primary button-full" type="submit">{{ __('Confirm password') }}</button>
</form>
@if (\Illuminate\Support\Facades\Route::has('passkey.confirm') && auth()->user()?->hasPasskeysEnabled())
<div class="passkey-alternative" data-passkey data-options-url="{{ route('passkey.confirm-options') }}" data-submit-url="{{ route('passkey.confirm') }}" hidden>
    <p class="auth-or"><span>{{ __('or') }}</span></p>
    <button class="button button-secondary button-full" type="button" data-passkey-start>@include('metribay::partials/icon', ['name'=>'key']) {{ __('Use a passkey') }}</button>
    <p class="passkey-error" role="alert" data-passkey-error hidden></p>
</div>
@endif
@endsection
