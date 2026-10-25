@extends('metribay::layouts/auth')
@section('title', 'Sign in')
@section('heading', 'Welcome back.')
@section('description', 'Sign in to see how your projects are doing.')
@section('content')
<form class="form-stack" method="POST" action="{{ route('login') }}">@csrf
    <div class="field"><label for="email">Email address</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus></div>
    <div class="field"><div class="field-label-row"><label for="password">Password</label><a class="text-link" href="{{ route('password.request') }}">Forgot password?</a></div><input id="password" name="password" type="password" autocomplete="current-password" required></div>
    <label class="checkbox-label"><input type="checkbox" name="remember" value="1" @checked(old('remember'))> Keep me signed in</label>
    <button class="button button-primary button-full" type="submit">Sign in @include('metribay::partials/icon', ['name'=>'arrow-up-right'])</button>
</form>
@if (\Illuminate\Support\Facades\Route::has('register'))
<p class="auth-alternative">New to Metribay? <a class="text-link" href="{{ route('register') }}">Create an account</a></p>
@endif
@endsection
