@extends('metribay::layouts/app')
@section('title', $project->name.' · '.__('Monthly report'))
@section('content')
@php
    $route = ['workspace' => $workspace->slug, 'project' => $project->id];
    $payload = $report?->payload;
    $monthKey = $payload['month'] ?? null;
@endphp
<header class="page-heading">
    <div>
        <a class="breadcrumb" href="{{ route('metribay.projects.show', $route) }}">@include('metribay::partials/icon',['name'=>'arrow-left']) {{ $project->name }}</a>
        <h1>{{ __('Monthly report') }}<span class="heading-dot">.</span></h1>
        @if($monthKey)<p class="page-description">{{ __('What changed in :month', ['month' => $rules->monthLabel($monthKey)]) }}</p>@endif
    </div>
</header>
@if(!$report)
<p class="data-note">@include('metribay::partials/icon',['name'=>'info'])<span>{{ __('A monthly report is saved after a calendar month closes. The numbers stay as they were.') }}</span></p>
@else
@php
    $start = \Carbon\CarbonImmutable::parse($monthKey.'-01');
    $previousKey = $start->subMonth()->format('Y-m');
    $yearKey = $start->subYear()->format('Y-m');
    $notes = ['partial' => false, 'per_day' => false];
    $hidden = \Metribay\Core\Services\MonthlySnapshot::OMITTED;
    $events = $rules->events($payload);
    $explore = route('metribay.projects.show', $route + ['from' => $start->toDateString(), 'to' => $start->endOfMonth()->toDateString()]);
@endphp
<div class="report-bar">
    <nav class="report-months" aria-label="{{ __('Monthly reports') }}">
        @if($older)<a class="report-step" rel="prev" href="{{ route('metribay.projects.report', $route + ['month' => $older->month->format('Y-m')]) }}" aria-label="{{ __('Previous report: :month', ['month' => $rules->monthLabel($older->month->format('Y-m'))]) }}">@include('metribay::partials/icon',['name'=>'arrow-left'])</a>@endif
        <form class="report-month-form" method="GET" action="{{ route('metribay.projects.report', $route) }}">
            <label class="sr-only" for="report-month">{{ __('Month') }}</label>
            <select id="report-month" name="month" data-submit-on-change>
                @foreach($months as $item)
                    @php $key = $item->month->format('Y-m'); @endphp
                    <option value="{{ $key }}" @selected($key === $monthKey)>{{ $rules->monthLabel($key) }}</option>
                @endforeach
            </select>
            <button class="button button-small" type="submit">{{ __('Open') }}</button>
        </form>
        @if($newer)<a class="report-step" rel="next" href="{{ route('metribay.projects.report', $route + ['month' => $newer->month->format('Y-m')]) }}" aria-label="{{ __('Next report: :month', ['month' => $rules->monthLabel($newer->month->format('Y-m'))]) }}">@include('metribay::partials/icon',['name'=>'chevron'])</a>@endif
    </nav>
    <a class="text-link" href="{{ $explore }}">{{ __('Explore this month') }} @include('metribay::partials/icon',['name'=>'chevron'])</a>
</div>
<p class="muted report-status">@if($report->sealed_at){{ __('Saved :date. This report does not change.', ['date' => $report->sealed_at->translatedFormat('j M Y')]) }}@else{{ __('This month is still open for late data.') }}@endif</p>

<section class="settings-section report-section" aria-labelledby="report-summary">
    <div class="section-intro"><h2 id="report-summary">{{ __('Summary') }}</h2></div>
    <div class="report-summary">@foreach($reading['sentences'] as $sentence)<p>{{ $sentence }}</p>@endforeach</div>
</section>
@php $kpis = $rules->kpis($payload); @endphp
@if($kpis !== [])
<section class="report-kpis" aria-labelledby="report-kpis">
    <h2 id="report-kpis" class="sr-only">{{ __('Key figures') }}</h2>
    @foreach($kpis as $tile)
        @php $figure = $tile['figure']; @endphp
        <article class="report-kpi">
            <h3>{{ $tile['label'] }}</h3>
            @if($figure['key'] !== 'key_events')<p class="report-kpi-source">{{ $tile['source']['label'] }}</p>@endif
            <p class="report-kpi-value">{{ $rules->number($figure, $figure['value']) }}</p>
            <p class="report-kpi-change">@include('metribay::partials.report-change', ['figure' => $figure, 'against' => 'previous', 'show' => false])<span>{{ __('vs :month', ['month' => $rules->monthLabel($previousKey, 'M')]) }}</span></p>
            <p class="report-kpi-change">@include('metribay::partials.report-change', ['figure' => $figure, 'against' => 'year_ago', 'show' => false])<span>{{ __('vs :month', ['month' => $rules->monthLabel($yearKey, 'M Y')]) }}</span></p>
        </article>
    @endforeach
</section>
@endif

@if($reading['actions'] !== [])
<section class="settings-section report-section" aria-labelledby="report-actions">
    <div class="section-intro"><h2 id="report-actions">{{ __('What to do next') }}</h2></div>
    <ol class="report-actions">
        @foreach($reading['actions'] as $action)
        <li><strong>{{ $action['title'] }}</strong><span>{{ $action['detail'] }}</span><span class="muted">{{ $action['evidence'] }}</span></li>
        @endforeach
    </ol>
</section>
@endif
@if($events !== [])
<section class="settings-section report-section" aria-labelledby="report-events">
    <div class="section-intro"><h2 id="report-events">{{ __('Events') }}</h2></div>
    <div>
        <ul class="report-events">
            @foreach(array_slice($events, 0, 8) as $event)
            <li><time datetime="{{ $event['date'] }}">{{ $rules->dayLabel($event['date']) }}</time><span>{{ $event['title'] }}</span></li>
            @endforeach
        </ul>
        @if(count($events) > 8)<p class="muted">{{ __('And :count more.', ['count' => count($events) - 8]) }}</p>@endif
    </div>
</section>
@endif

@foreach($payload['sources'] as $source)
@php
    $figures = array_values(array_filter($source['metrics'], fn (array $figure): bool => ! in_array($figure['key'], $hidden, true) && $figure['unit'] !== 'status'
        && ($figure['value'] !== null || $figure['previous'] !== null || $figure['year_ago'] !== null)));
    $health = $source['health'] ?? [];
@endphp
@if($figures !== [])
<section class="settings-section report-section" aria-labelledby="report-source-{{ $source['id'] }}">
    <div class="section-intro">
        <h2 id="report-source-{{ $source['id'] }}">{{ $source['provider'] ?? $source['label'] }}</h2>
        @if(($source['provider'] ?? null) !== null)<p class="report-source-label">{{ $source['label'] }}</p>@endif
        <p>{{ __('Reporting timezone: :timezone', ['timezone' => $source['timezone']]) }}</p>
        @if($source['added_on'])<p class="report-note">{{ __('Data starts on :date, during the month.', ['date' => $rules->dayLabel($source['added_on'])]) }}</p>@endif
        @if($source['paused_on'])<p class="report-note">{{ __('Collection stopped on :date.', ['date' => $rules->dayLabel($source['paused_on'])]) }}</p>@endif
        @if(($health['failed_runs'] ?? 0) > 0)<p class="report-note">{{ __('Failed runs in the month: :count of :runs.', ['count' => $health['failed_runs'], 'runs' => $health['runs']]) }}</p>@endif
    </div>
    <div>
        @if(is_array($source['chart'] ?? null) && isset($source['chart']['months']))
            @include('metribay::partials.report-chart', ['chart' => $source['chart'], 'currency' => collect($source['metrics'])->firstWhere('key', $source['chart']['key'])['currency'] ?? null, 'label' => __(\Metribay\Core\Metrics\MetricRegistry::get($source['chart']['key'])->label)])
        @endif
        <table class="report-table">
            <caption class="sr-only">{{ __(':source in :month', ['source' => $source['label'], 'month' => $rules->monthLabel($monthKey)]) }}</caption>
            <thead><tr>
                <th scope="col">{{ __('Metric') }}</th>
                <th scope="col">{{ $rules->monthLabel($monthKey, 'M Y') }}</th>
                <th scope="col">{{ $rules->monthLabel($previousKey, 'M Y') }}</th>
                <th scope="col">{{ $rules->monthLabel($yearKey, 'M Y') }}</th>
                <th scope="col">{{ __('3-month average') }}</th>
            </tr></thead>
            <tbody>
            @foreach($figures as $figure)
                @php
                    $flags = $figure['comparability'] ?? [];
                    $notes['partial'] = $notes['partial'] || array_intersect($flags, ['partial', 'previous_partial', 'year_partial']) !== [];
                    foreach (['previous', 'year_ago'] as $against) { $notes['per_day'] = $notes['per_day'] || ($rules->difference($figure, $against)['per_day'] ?? false); }
                @endphp
                <tr>
                    <th scope="row">{{ __(\Metribay\Core\Metrics\MetricRegistry::get($figure['key'])->label) }}</th>
                    <td class="report-value">{{ $rules->number($figure, $figure['value']) }}@if(in_array('partial', $flags, true))<span class="report-mark" aria-hidden="true">*</span><span class="sr-only">{{ __('Part of the month') }}</span>@endif</td>
                    <td data-label="{{ $rules->monthLabel($previousKey, 'M Y') }}">@include('metribay::partials.report-change', ['figure' => $figure, 'against' => 'previous', 'show' => true])</td>
                    <td data-label="{{ $rules->monthLabel($yearKey, 'M Y') }}">@include('metribay::partials.report-change', ['figure' => $figure, 'against' => 'year_ago', 'show' => true])</td>
                    <td data-label="{{ __('3-month average') }}">@include('metribay::partials.report-change', ['figure' => $figure, 'against' => 'trailing_average', 'show' => true])</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>
@endif
@endforeach

@if($payload['queries'] !== [])
<section class="settings-section report-section" aria-labelledby="report-queries">
    <div class="section-intro"><h2 id="report-queries">{{ __('Top queries') }}</h2></div>
    <table class="report-table report-queries">
        <thead><tr><th scope="col">{{ __('Query') }}</th><th scope="col">{{ __('Clicks') }}</th><th scope="col">{{ $rules->monthLabel($previousKey, 'M Y') }}</th><th scope="col">{{ __('Position') }}</th></tr></thead>
        <tbody>
        @foreach($payload['queries'] as $query)
            @php $clicks = ['key' => 'clicks', 'unit' => 'count', 'aggregation' => 'latest', 'currency' => null, 'value' => $query['clicks'], 'previous' => $query['previous_clicks']]; @endphp
            <tr>
                <th scope="row">{{ $query['query'] }}</th>
                <td class="report-value">{{ $rules->number($clicks, $query['clicks']) }}</td>
                <td data-label="{{ $rules->monthLabel($previousKey, 'M Y') }}">@include('metribay::partials.report-change', ['figure' => $clicks, 'against' => 'previous', 'show' => true])</td>
                <td data-label="{{ __('Position') }}">{{ $query['position'] === null ? '—' : \Illuminate\Support\Number::format($query['position'], 1, locale: app()->getLocale()) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</section>
@endif

<footer class="report-footnotes muted">
    <p>{{ __('A dash is unknown, not zero.') }}</p>
    @if($notes['partial'])<p>{{ __('* Only part of the month is known, so it is compared per known day or not at all.') }}</p>@endif
    @if($notes['per_day'])<p>{{ __('† Compared per day, because the months differ in length or days are missing.') }}</p>@endif
</footer>
@endif
@endsection
