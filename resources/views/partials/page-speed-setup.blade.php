@php($managedPageSpeed = app(\Metribay\Core\Contracts\PageSpeedCredentials::class))
@if($managedPageSpeed->managed() && !$managedPageSpeed->available())
<p class="notice">{{ __('Page speed is temporarily unavailable. Please try again later.') }}</p>
@else
<p class="muted">{{ __('Track a public page with a daily speed test and real-user data where available.') }}</p>
<form class="form-stack" method="POST" action="{{ route('metribay.sources.setup', $workspace->slug) }}">
    @csrf<input type="hidden" name="action" value="page_speed">
    <div class="field"><label for="page-speed-url">{{ __('Page URL') }}</label><input id="page-speed-url" type="url" name="url" value="{{ old('url', $project->domain ? 'https://'.preg_replace('~^https?://~', '', $project->domain) : '') }}" placeholder="https://example.com/" maxlength="500" required><p class="field-hint">{{ __('Use a public HTTPS page. Private pages and pages that require sign-in cannot be tested.') }}</p></div>
    <div class="field"><label for="page-speed-device">{{ __('Device') }}</label><select id="page-speed-device" name="strategy"><option value="mobile" @selected(old('strategy', 'mobile') === 'mobile')>{{ __('Mobile') }}</option><option value="desktop" @selected(old('strategy') === 'desktop')>{{ __('Desktop') }}</option></select></div>
    <div><button class="button button-primary" type="submit">{{ __('Continue') }} →</button></div>
</form>
@endif
