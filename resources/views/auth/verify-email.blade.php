@extends('metribay::layouts/auth')
@section('title', 'Verify email')
@section('heading', 'Check your inbox.')
@section('description', 'Open the verification link in your email to finish setting up your account.')
@section('content')
<div class="form-stack">
    <p class="muted">Signed in as <strong>{{ auth()->user()?->email }}</strong>.</p>
    <form method="POST" action="{{ route('verification.send') }}">@csrf<button class="button button-primary button-full" type="submit">Send a new verification link</button></form>
    <form method="POST" action="{{ route('logout') }}">@csrf<button class="button button-quiet button-full" type="submit">Sign out</button></form>
</div>
@endsection
