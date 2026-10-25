@extends('metribay::layouts/auth')
@section('title', 'Confirm password')
@section('heading', 'Confirm it’s you.')
@section('description', 'Enter your password to continue with this change.')
@section('content')
<form class="form-stack" method="POST" action="{{ route('password.confirm') }}">@csrf
    <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required autofocus></div>
    <button class="button button-primary button-full" type="submit">Confirm password</button>
</form>
@endsection
