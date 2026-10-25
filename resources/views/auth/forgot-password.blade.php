@extends('metribay::layouts/auth')
@section('title', 'Reset password')
@section('heading', 'Forgot your password?')
@section('description', 'We’ll email you a link to choose a new one.')
@section('content')
<form class="form-stack" method="POST" action="{{ route('password.email') }}">@csrf
    <div class="field"><label for="email">Email address</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus></div>
    <button class="button button-primary button-full" type="submit">Send reset link</button>
</form>
<p class="auth-alternative"><a class="text-link" href="{{ route('login') }}">Back to sign in</a></p>
@endsection
