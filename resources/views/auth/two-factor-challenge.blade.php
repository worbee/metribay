@extends('metribay::layouts/auth')
@section('title', __('Two-factor authentication'))
@section('heading', __('One more check.'))
@section('description', __('Enter the code from your authenticator app.'))
@section('content')
<form class="form-stack" method="POST" action="{{ route('two-factor.login') }}" data-two-factor-form>@csrf
    <div class="field" data-authenticator-field><label for="code">{{ __('Authentication code') }}</label><input id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" maxlength="6" required autofocus><p class="field-hint">{{ __('The six-digit code changes every 30 seconds.') }}</p></div>
    <div class="field" data-recovery-field hidden><label for="recovery_code">{{ __('Recovery code') }}</label><input id="recovery_code" name="recovery_code" autocomplete="off" disabled><p class="field-hint">{{ __('Each recovery code can be used once.') }}</p></div>
    <button class="button button-primary button-full" type="submit">{{ __('Verify and sign in') }}</button>
    <button class="button button-quiet" type="button" data-two-factor-toggle>{{ __('Use a recovery code') }}</button>
</form>
@endsection
