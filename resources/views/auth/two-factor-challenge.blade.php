@extends('metribay::layouts/auth')
@section('title', 'Two-factor authentication')
@section('heading', 'One more check.')
@section('description', 'Enter the code from your authenticator app.')
@section('content')
<form class="form-stack" method="POST" action="{{ route('two-factor.login') }}" data-two-factor-form>@csrf
    <div class="field" data-authenticator-field><label for="code">Authentication code</label><input id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" maxlength="6" required autofocus><p class="field-hint">The six-digit code changes every 30 seconds.</p></div>
    <div class="field" data-recovery-field hidden><label for="recovery_code">Recovery code</label><input id="recovery_code" name="recovery_code" autocomplete="off" disabled><p class="field-hint">Each recovery code can be used once.</p></div>
    <button class="button button-primary button-full" type="submit">Verify and sign in</button>
    <button class="button button-quiet" type="button" data-two-factor-toggle>Use a recovery code</button>
</form>
@endsection
