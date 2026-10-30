@extends('metribay::layouts/auth')
@section('title', __('Confirm password'))
@section('heading', __('Confirm it’s you.'))
@section('description', __('Enter your password to continue with this change.'))
@section('content')
<form class="form-stack" method="POST" action="{{ route('password.confirm') }}">@csrf
    <div class="field"><label for="password">{{ __('Password') }}</label><input id="password" name="password" type="password" autocomplete="current-password" required autofocus></div>
    <button class="button button-primary button-full" type="submit">{{ __('Confirm password') }}</button>
</form>
@endsection
