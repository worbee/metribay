@if (session('status'))
    <div class="notice notice-success" role="status">
        @include('metribay::partials/icon', ['name' => 'check'])
        <div>{{ match(session('status')) {
            'verification-link-sent' => __('A new verification link has been sent to your email.'),
            'profile-information-updated' => __('Your details have been updated.'),
            'password-updated' => __('Your password has been updated.'),
            'two-factor-authentication-enabled' => __('Two-factor setup started. Confirm a code to finish.'),
            'two-factor-authentication-confirmed' => __('Two-factor authentication is enabled.'),
            'recovery-codes-generated' => __('New recovery codes generated. Store them somewhere private.'),
            'two-factor-authentication-disabled' => __('Two-factor authentication is disabled.'),
            default => session('status'),
        } }}</div>
    </div>
@endif
@if (session(\Metribay\Core\Http\Middleware\DemoMode::NOTICE_KEY))
    <div class="notice notice-warning" role="status">
        @include('metribay::partials/icon', ['name' => 'alert'])
        <div>{{ session(\Metribay\Core\Http\Middleware\DemoMode::NOTICE_KEY) }}</div>
    </div>
@endif
@php($validationErrors = collect($errors->getBags())->flatMap(fn ($bag) => $bag->all())->unique())
@if ($validationErrors->isNotEmpty())
    <div class="notice notice-error" role="alert" tabindex="-1" data-validation-errors>
        @include('metribay::partials/icon', ['name' => 'alert'])
        <div><strong>{{ __('Please check the following:') }}</strong><ul>@foreach ($validationErrors as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    </div>
@endif
