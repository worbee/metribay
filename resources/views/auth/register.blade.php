@extends('metribay::layouts/auth')
@section('title', 'Create account')
@section('heading', 'Your projects, together.')
@section('description', 'Create your account and connect your first project.')
@section('content')
<form class="form-stack" method="POST" action="{{ route('register') }}">@csrf
    <div class="field"><label for="name">Your name</label><input id="name" name="name" value="{{ old('name') }}" autocomplete="name" maxlength="120" required autofocus></div>
    <div class="field"><label for="email">Email address</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required></div>
    <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required><p class="field-hint">At least 12 characters.</p></div>
    <div class="field"><label for="password_confirmation">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required></div>
    <button class="button button-primary button-full" type="submit">Create account @include('metribay::partials/icon', ['name'=>'arrow-up-right'])</button>
</form>
<p class="auth-alternative">Already have an account? <a class="text-link" href="{{ route('login') }}">Sign in</a></p>
@endsection
