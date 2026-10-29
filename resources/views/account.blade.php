@extends('metribay::layouts/app')
@section('title', 'Account & preferences')
@section('content')
<header class="page-heading"><div><div class="eyebrow">Your account</div><h1>Account & preferences</h1><p class="page-description">Manage your profile, security, and how Metribay looks.</p></div></header>
<section class="settings-section" aria-labelledby="profile-heading">
    <div class="section-intro"><h2 id="profile-heading">Profile</h2><p>Your name and sign-in email.</p></div>
    <form class="form-stack form-width" method="POST" action="{{ url('/user/profile-information') }}">@csrf @method('PUT')
        <div class="field"><label for="profile-name">Name</label><input id="profile-name" name="name" value="{{ old('name', $user->name) }}" autocomplete="name" required maxlength="120"></div>
        <div class="field"><label for="profile-email">Email address</label><input id="profile-email" name="email" type="email" value="{{ old('email', $user->email) }}" autocomplete="email" required></div>
        <div><button class="button button-primary" type="submit">Save profile</button></div>
    </form>
</section>
<section class="settings-section" aria-labelledby="password-heading">
    <div class="section-intro"><h2 id="password-heading">Password</h2><p>Use a strong, unique password.</p></div>
    <form class="form-stack form-width" method="POST" action="{{ url('/user/password') }}">@csrf @method('PUT')
        <div class="field"><label for="current_password">Current password</label><input id="current_password" name="current_password" type="password" autocomplete="current-password" required></div>
        <div class="form-grid"><div class="field"><label for="new-password">New password</label><input id="new-password" name="password" type="password" autocomplete="new-password" minlength="14" required></div><div class="field"><label for="password_confirmation">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="14" required></div></div>
        <p class="field-hint">At least 14 characters.</p><div><button class="button button-primary" type="submit">Update password</button></div>
    </form>
</section>
@if (\Illuminate\Support\Facades\Route::has('two-factor.enable'))
<section class="settings-section" aria-labelledby="two-factor-heading">
    <div class="section-intro"><h2 id="two-factor-heading">Two-factor authentication</h2><p>An extra layer of protection for your account.</p></div>
    <div class="form-stack form-width">
    @if (!$user->two_factor_secret)
        <p class="muted">Use an authenticator app to verify your identity when you sign in.</p>
        <form method="POST" action="{{ route('two-factor.enable') }}">@csrf<button class="button button-primary" type="submit">Enable two-factor authentication</button></form>
    @elseif ((int)session('auth.password_confirmed_at', 0) < time() - (int)config('auth.password_timeout', 10800))
        <p class="muted">Confirm your password to view two-factor setup and recovery details.</p>
        <a class="button button-secondary" href="{{ route('password.confirm') }}">Confirm password</a>
    @elseif (!$user->two_factor_confirmed_at)
        <p>Scan this QR code in your authenticator app, then enter a code to finish setup.</p>
        <div class="qr-code">{!! $user->twoFactorQrCodeSvg() !!}</div>
        <details class="disclosure"><summary>Enter setup key manually</summary><code class="secret-code">{{ decrypt($user->two_factor_secret) }}</code></details>
        <form class="form-stack" method="POST" action="{{ route('two-factor.confirm') }}">@csrf<div class="field"><label for="setup-code">Authentication code</label><input id="setup-code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></div><div><button class="button button-primary" type="submit">Confirm setup</button></div></form>
    @else
        <div class="inline-status">@include('metribay::partials/icon', ['name'=>'check']) Two-factor authentication is enabled.</div>
        <details class="disclosure"><summary>View recovery codes</summary><p class="field-hint">Save these somewhere private. Each code can be used once.</p><div class="recovery-codes">@foreach ($user->recoveryCodes() as $code)<code>{{ $code }}</code>@endforeach</div></details>
        @if (\Illuminate\Support\Facades\Route::has('two-factor.recovery-codes'))
        <form method="POST" action="{{ url('/user/two-factor-recovery-codes') }}">@csrf<button class="button button-secondary" type="submit">Generate new recovery codes</button></form>
        @endif
        <form method="POST" action="{{ route('two-factor.disable') }}" data-confirm="Disable two-factor authentication for your account?">@csrf @method('DELETE')<button class="button button-danger-quiet" type="submit">Disable two-factor authentication</button></form>
    @endif
    </div>
</section>
@endif
@include('metribay::partials/appearance')
@endsection
