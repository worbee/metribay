@php
    $analyticsPage = \Metribay\Core\Services\UsageAnalytics::page(request());
    $analyticsEdition = config('metribay.demo.enabled') ? 'demo' : (in_array(config('metribay.edition'), ['cloud', 'desktop'], true) ? config('metribay.edition') : 'personal');
    $analyticsEvent = session('analytics_event');
    $analyticsStrings = ['Privacy settings', 'Your privacy choices', 'Share usage statistics?', 'Help improve Metribay by sharing limited usage statistics. This is optional and stays off unless you agree.', 'With your permission, we use Umami to measure visits and how Metribay is used. No advertising or session recordings. Your choice applies to this website and Metribay Cloud.', 'Accept analytics', 'Reject analytics', 'Preferences', 'Save preferences', 'Necessary', 'Required for sign-in, security and remembering this choice.', 'Analytics', 'Optional page views and usage events sent to s.worbee.com. No names, emails or project data.', 'Privacy policy', 'Close', 'The analytics server receives your IP address to process requests.'];
@endphp
@if($analyticsPage)
<script type="application/json" id="metribay-analytics">@php($analyticsPayload = ['edition' => $analyticsEdition, 'page' => $analyticsPage, 'event' => in_array($analyticsEvent, ['signup_submitted', 'signup_completed'], true) ? $analyticsEvent : null, 'script' => config('metribay.analytics.script_url'), 'website' => config('metribay.analytics.website_id'), 'strings' => collect($analyticsStrings)->mapWithKeys(fn ($text) => [$text => __($text)])])
@json($analyticsPayload, JSON_HEX_TAG | JSON_HEX_AMP)</script>
@endif
