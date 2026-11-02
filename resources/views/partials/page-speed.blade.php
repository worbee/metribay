@php
    $lab = collect($source['metrics'])->filter(fn($metric) => str_starts_with($metric['key'], 'lab_'));
    $context = collect($source['all_metrics'] ?? $source['metrics'])->firstWhere('key', 'lab_score')['context'] ?? [];
    $pageField = collect($source['metrics'])->filter(fn($metric) => in_array($metric['key'], ['crux_lcp', 'crux_inp', 'crux_cls']) && $metric['value'] !== null);
    $siteField = collect($source['metrics'])->filter(fn($metric) => str_starts_with($metric['key'], 'crux_origin_') && $metric['value'] !== null);
    $device = ($source['page_speed']['strategy'] ?? 'mobile') === 'desktop' ? __('Desktop') : __('Mobile');
@endphp
<div class="page-speed-results">
<p class="field-hint">{{ $source['page_speed']['url'] ?? '' }} · {{ $device }}</p>
@if(!empty($context['audited_url']) && $context['audited_url'] !== ($source['page_speed']['url'] ?? ''))<p class="field-hint">{{ __('Page URL') }}: {{ $context['audited_url'] }}</p>@endif
@if($lab->isNotEmpty())<h3>{{ __('Lab tests') }}</h3>
<p class="field-hint">{{ __('Daily Lighthouse tests · scores and timings are snapshots, not totals.') }} @if(!empty($context['tested_at'])){{ __('Tested :date', ['date' => \Carbon\CarbonImmutable::parse($context['tested_at'])->utc()->translatedFormat('j M Y, H:i').' UTC']) }}@endif @if(!empty($context['lighthouse_version'])) · Lighthouse {{ $context['lighthouse_version'] }}@endif</p>
<div class="metric-grid">@foreach($lab as $metric)@include('metribay::partials/metric',['metric'=>$metric,'sourceId'=>$source['id'],'compact'=>false,'days'=>$days,'events'=>$source['events'] ?? []])@endforeach</div>@endif
<h3>{{ __('Real users') }}</h3>
<p class="field-hint">{{ __('CrUX · 75th percentile over rolling 28-day windows. Page and whole-site results are shown separately.') }}</p>
@if(($context['field_status'] ?? '') === 'unavailable')<p class="notice notice-warning">{{ __('Real-user data could not be refreshed. Lab results are still available.') }}</p>
@elseif(($context['field_status'] ?? '') === 'no_data')<p class="notice">{{ __('Not enough real-user data yet. Google only reports pages and sites with enough traffic.') }}</p>@endif
@if(($context['history_status'] ?? '') === 'unavailable')<p class="field-hint">{{ __('Some real-user history could not be refreshed.') }}</p>@endif
@if($pageField->isNotEmpty())<h4>{{ __('This page') }}</h4><div class="metric-grid">@foreach($pageField as $metric)@include('metribay::partials/metric',['metric'=>$metric,'sourceId'=>$source['id'],'compact'=>false,'days'=>$days,'events'=>[]])@endforeach</div>@endif
@if($siteField->isNotEmpty())<h4>{{ __('Whole site') }}</h4><p class="field-hint">{{ __('Site-level data covers all pages on this origin, not just the tracked page.') }}</p><div class="metric-grid">@foreach($siteField as $metric)@include('metribay::partials/metric',['metric'=>$metric,'sourceId'=>$source['id'],'compact'=>false,'days'=>$days,'events'=>[]])@endforeach</div>@endif

</div>
