@if (session('status'))
    <div class="notice notice-success" role="status">
        @include('metribay::partials/icon', ['name' => 'check'])
        <div>{{ match(session('status')) {
            'verification-link-sent' => 'A new verification link has been sent to your email.',
            'profile-information-updated' => 'Your profile has been updated.',
            'password-updated' => 'Your password has been updated.',
            'two-factor-authentication-enabled' => 'Two-factor setup started. Confirm a code to finish.',
            'two-factor-authentication-confirmed' => 'Two-factor authentication is enabled.',
            'recovery-codes-generated' => 'New recovery codes generated. Store them somewhere private.',
            'two-factor-authentication-disabled' => 'Two-factor authentication is disabled.',
            default => session('status'),
        } }}</div>
    </div>
@endif
@if ($errors->any())
    <div class="notice notice-error" role="alert" tabindex="-1" data-validation-errors>
        @include('metribay::partials/icon', ['name' => 'alert'])
        <div><strong>Please check the following:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    </div>
@endif
