@php
    $byKey = collect($source['metrics'])->keyBy('key');
    $lab = collect(['lab_score', 'lab_lcp', 'lab_cls', 'lab_tbt'])->map(fn($key) => $byKey->get($key))->filter();
    $context = collect($source['all_metrics'] ?? $source['metrics'])->firstWhere('key', 'lab_score')['context'] ?? [];
    $pageField = collect(['crux_lcp', 'crux_inp', 'crux_cls'])->map(fn($key) => $byKey->get($key))->filter(fn($metric) => $metric && $metric['value'] !== null);
    $siteField = collect(['crux_origin_lcp', 'crux_origin_inp', 'crux_origin_cls'])->map(fn($key) => $byKey->get($key))->filter(fn($metric) => $metric && $metric['value'] !== null);
    $device = ($source['page_speed']['strategy'] ?? 'mobile') === 'desktop' ? __('Desktop') : __('Mobile');
    $fieldObserved = $pageField->merge($siteField)->pluck('observed_date')->filter()->max();
    $tile = fn($metric) => ['metric' => $metric, 'source' => $source, 'label' => null, 'size' => 'n', 'span' => null, 'rows' => 1, 'solo' => false];
@endphp
<div class="page-speed-results">
<p class="speed-target">@if($multiple ?? false)<strong>{{ $source['label'] }}</strong> · @endif{{ $source['page_speed']['url'] ?? '' }} · {{ $device }}@if(!empty($context['audited_url']) && $context['audited_url'] !== ($source['page_speed']['url'] ?? '')) · {{ __('Page URL') }}: {{ $context['audited_url'] }}@endif</p>
@if($lab->isNotEmpty())
<div class="speed-block">
<h3 class="speed-caption"><span>{{ __('Lab tests') }}</span><small>{{ __('Daily Lighthouse tests · scores and timings are snapshots, not totals.') }}@if(!empty($context['tested_at'])) {{ __('Tested :date', ['date' => \Carbon\CarbonImmutable::parse($context['tested_at'])->utc()->translatedFormat('j M Y, H:i').' UTC']) }}@endif @if(!empty($context['lighthouse_version'])) · Lighthouse {{ $context['lighthouse_version'] }}@endif</small></h3>
<div class="speed-grid">@foreach($lab as $metric)@include('metribay::partials/metric', ['card' => $tile($metric), 'short' => true, 'observedInCaption' => !empty($context['tested_at'])])@endforeach</div>
</div>
@endif
<div class="speed-block">
<h3 class="speed-caption"><span>{{ __('Real users') }}</span><small>{{ __('CrUX · 75th percentile over rolling 28-day windows. Page and whole-site results are shown separately.') }}@if($fieldObserved) {{ __('Observed :date', ['date' => \Carbon\CarbonImmutable::parse($fieldObserved)->translatedFormat('j M')]) }}@endif</small></h3>
@if(($context['field_status'] ?? '') === 'unavailable')<p class="notice notice-warning">{{ __('Real-user data could not be refreshed. Lab results are still available.') }}</p>
@elseif(($context['field_status'] ?? '') === 'no_data')<p class="notice">{{ __('Not enough real-user data yet. Google only reports pages and sites with enough traffic.') }}</p>@endif
@if(($context['history_status'] ?? '') === 'unavailable')<p class="field-hint">{{ __('Some real-user history could not be refreshed.') }}</p>@endif
@if($pageField->isNotEmpty() || $siteField->isNotEmpty())
<div class="field-grid">
@if($pageField->isNotEmpty())<div><h4>{{ __('This page') }}</h4><div class="speed-grid">@foreach($pageField as $metric)@include('metribay::partials/metric', ['card' => $tile($metric), 'short' => true, 'observedInCaption' => true])@endforeach</div></div>@endif
@if($siteField->isNotEmpty())<div><h4 title="{{ __('Site-level data covers all pages on this origin, not just the tracked page.') }}">{{ __('Whole site') }}</h4><div class="speed-grid">@foreach($siteField as $metric)@include('metribay::partials/metric', ['card' => $tile($metric), 'short' => true, 'observedInCaption' => true])@endforeach</div></div>@endif
</div>
@endif
</div>
</div>
