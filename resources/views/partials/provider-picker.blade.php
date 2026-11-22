@php($groupCopy = [
    'analytics' => [__('Website analytics'), __('How many people visit your site, what they read and where they come from. Pick the analytics tool your site already runs.')],
    'search' => [__('Search engines'), __('How often your pages appear in Google and Bing results, and how often people click them. Needs a site you have verified with the search engine, not a tracking script.')],
    'health' => [__('Uptime and speed'), __('Whether your site is up, how fast it responds and how quickly pages load for visitors.')],
    'content' => [__('Publishing'), __('New pages and posts as you publish them. Each one appears as an event on your charts, so you can see what changed afterwards.')],
    'code' => [__('Code and releases'), __('Development activity and downloads: commits, pull requests, releases and plugin downloads.')],
    'social' => [__('Social followers'), __('Follower counts of public social accounts, recorded once a day.')],
    'revenue' => [__('Revenue'), __('Revenue, recurring revenue and paying subscribers from your billing system.')],
    'other' => [__('Other'), null],
])
@php($providerSummaries = [
    'ga4' => __('Google’s analytics for websites and apps'),
    'plausible' => __('Privacy-friendly analytics, cloud or self-hosted'),
    'umami' => __('Open-source analytics on your own server'),
    'matomo' => __('Open-source analytics, cloud or self-hosted'),
    'posthog' => __('Product analytics: users, page views and key events'),
    'gsc' => __('Clicks and impressions in Google Search'),
    'bing' => __('Clicks, impressions and indexed pages in Bing'),
    'uptimerobot' => __('Uptime, outages and response time'),
    'pagespeed' => __('Lighthouse score and Core Web Vitals for a page'),
    'sitemap' => __('New pages found in your sitemap'),
    'feed' => __('New posts from a blog, podcast or news feed'),
    'github' => __('Commits, pull requests, issues and releases'),
    'github_releases' => __('Downloads of release files'),
    'azure_devops' => __('Commits, pull requests, pipeline runs and bugs'),
    'wporg' => __('Daily downloads of a WordPress.org plugin'),
    'bluesky' => __('Followers of a Bluesky account'),
    'mastodon' => __('Followers of a Mastodon account'),
    'paddle' => __('Revenue, MRR and paying subscribers'),
])
<h2>{{ __('Where is the data?') }}</h2>
<p class="muted">{{ __('Choose the tool that already has the numbers. You can add more data sources to this project later.') }} {{ __('Not sure where to start? Most websites begin with their analytics tool and Google Search Console.') }}</p>
<div class="field provider-filter"><label for="provider-search">{{ __('Find a provider') }}</label><input id="provider-search" type="search" autocomplete="off" placeholder="{{ __('Name or what it measures…') }}" data-provider-search></div>
<p class="notice" data-provider-empty hidden>{{ __('No provider matches. Clear the search to see them all.') }}</p>
@foreach(\Metribay\Core\Services\ProviderCatalog::grouped(array_keys($providers)) as $group => $kinds)
<section class="provider-group" data-provider-group aria-labelledby="provider-group-{{ $group }}">
<h3 id="provider-group-{{ $group }}">{{ $groupCopy[$group][0] }}</h3>
@if($groupCopy[$group][1])<p class="provider-group-description">{{ $groupCopy[$group][1] }}</p>@endif
<ul class="provider-choices" role="list">
@foreach($kinds as $kind)
@php($provider = $providers[$kind])
@php($docsUrl = \Metribay\Core\Services\ProviderCatalog::docsUrl($kind))
@php($added = (int) ($providerCounts[$kind] ?? 0))
<li class="provider-card @if($docsUrl) has-docs @endif" data-provider-row>
<form method="POST" action="{{ route('metribay.sources.setup', $workspace->slug) }}">@csrf @include('metribay::partials/setup-context')<input type="hidden" name="action" value="provider"><input type="hidden" name="kind" value="{{ $kind }}">
<button class="provider-choice" type="submit"><span class="provider-choice-text"><span class="provider-name">{{ $provider['label'] }}@if($added > 0) <span class="provider-count">{{ trans_choice('{1} :count added|[2,*] :count added', $added, ['count' => $added]) }}</span>@endif</span>@isset($providerSummaries[$kind])<small>{{ $providerSummaries[$kind] }}</small>@endisset @unless(\Metribay\Core\Services\ProviderCatalog::requiresConnection($kind))<small class="provider-tag">@include('metribay::partials/icon', ['name' => 'check']) {{ __('No sign-in needed') }}</small>@endunless</span><span class="provider-choice-arrow" aria-hidden="true">→</span></button>
</form>
@if($docsUrl)<a class="provider-docs" href="{{ $docsUrl }}" target="_blank" rel="noopener" aria-label="{{ __('Setup guide') }}: {{ $provider['label'] }} ({{ __('opens in a new tab') }})">{{ __('Setup guide') }} @include('metribay::partials/icon', ['name' => 'arrow-up-right'])</a>@endif
</li>
@endforeach
</ul>
</section>
@endforeach
