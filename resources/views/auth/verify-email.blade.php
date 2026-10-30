@extends('metribay::layouts/auth')
@section('title', __('Verify email'))
@section('heading', __('Check your inbox.'))
@section('description', __('Open the verification link in your email to finish setting up your account.'))
@section('content')
<div class="form-stack">
    <p class="muted">{{ __('Signed in as') }} <strong>{{ auth()->user()?->email }}</strong>.</p>
    <form method="POST" action="{{ route('verification.send') }}">@csrf<button class="button button-primary button-full" type="submit">{{ __('Send a new verification link') }}</button></form>
    <form method="POST" action="{{ route('logout') }}">@csrf<button class="button button-quiet button-full" type="submit">{{ __('Sign out') }}</button></form>
</div>
@endsection
