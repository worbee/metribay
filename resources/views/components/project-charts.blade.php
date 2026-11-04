<div data-hourly-refresh>
@php
    $hideAction = ($canEdit ?? false) ? route('metribay.charts.hide', ['workspace'=>$workspace->slug,'project'=>$project->id]) : null;
    $settingsUrl = route('metribay.projects.edit',['workspace'=>$workspace->slug,'project'=>$project->id]);
    $attention = $statuses['failed'] ?? 0;
    $states = array_filter([
        'failed' => $attention ? trans_choice('{1} :count needs attention|[2,*] :count need attention', $attention, ['count' => $attention]) : null,
        'delayed' => ($statuses['delayed'] ?? 0) ? trans_choice('{1} :count delayed|[2,*] :count delayed', $statuses['delayed'], ['count' => $statuses['delayed']]) : null,
        ($statuses['pending'] ?? 0) ? trans_choice('{1} :count importing|[2,*] :count importing', $statuses['pending'], ['count' => $statuses['pending']]) : null,
        ($statuses['disabled'] ?? 0) ? trans_choice('{1} :count paused|[2,*] :count paused', $statuses['disabled'], ['count' => $statuses['disabled']]) : null,
    ]);
    $domainUrl = $project->domain ? (str_contains($project->domain, '://') ? $project->domain : 'https://'.$project->domain) : null;
    $domainUrl = $domainUrl && filter_var($domainUrl, FILTER_VALIDATE_URL) && in_array(parse_url($domainUrl, PHP_URL_SCHEME), ['http', 'https'], true) ? $domainUrl : null;
    // The events page lists 7, 30 or 90 days.
    $eventsUrl = route('metribay.events.index', ['workspace'=>$workspace->slug,'project'=>$project->id,'days'=>$period->isPreset() ? $days : ($days <= 7 ? 7 : ($days <= 30 ? 30 : 90))]);
    [$rangeFrom, $rangeTo] = array_map(fn ($date) => $date->toDateString(), $period->window('UTC', \Carbon\CarbonImmutable::now('UTC')));
    $rangeMax = \Carbon\CarbonImmutable::now('UTC')->addDay()->toDateString();
    $summaryLabels = ['traffic'=>__('Visits'),'search'=>__('Search clicks'),'development'=>__('Commits · PRs'),'issues'=>__('Open issues'),'downloads'=>__('Downloads'),'release_downloads'=>__('Release downloads'),'uptime'=>__('Uptime · 24h'),'speed'=>__('Page speed · lowest')];
@endphp
<header class="project-head">
    <div class="project-head-main">
        <a class="breadcrumb" href="{{ route('metribay.dashboard', ['workspace'=>$workspace->slug, ...$period->query()]) }}">@include('metribay::partials/icon',['name'=>'arrow-left']) {{ __('All projects') }}</a>
        <div class="project-title">@if($project->domain)<img class="project-favicon" src="{{ route('metribay.projects.favicon', ['workspace'=>$workspace->slug, 'project'=>$project->id, 'v'=>substr(hash('sha256', $project->domain), 0, 12)]) }}" width="22" height="22" alt="" decoding="async">@endif<h1>{{ $project->name }}</h1>@if($domainUrl)<a class="project-domain" href="{{ $domainUrl }}" target="_blank" rel="noopener noreferrer">{{ $project->domain }} @include('metribay::partials/icon',['name'=>'arrow-up-right'])</a>@elseif($project->domain)<span class="project-domain">{{ $project->domain }}</span>@endif</div>
        @if($sources)
        <p class="project-status"><span @class(['status-dot', 'is-bad' => $attention, 'is-warn' => !$attention && ($statuses['delayed'] ?? 0) + ($statuses['pending'] ?? 0), 'dot-neutral' => count($sources) === ($statuses['disabled'] ?? 0)]) aria-hidden="true"></span><span>{{ trans_choice('{1} :count data source|[2,*] :count data sources', count($sources), ['count' => count($sources)]) }} · @forelse($states as $state => $label)@if(!$loop->first) · @endif@if(in_array($state, ['failed', 'delayed'], true))@php($affected = collect($sources)->where('status', $state)->values())<a class="text-link" href="#{{ $affected[0]['attention_anchor'] }}">{{ $label }}</a>@if($affected->count() > 1) <span class="project-attention-sources">(@foreach($affected as $source)@if(!$loop->first), @endif<a class="text-link" href="#{{ $source['attention_anchor'] }}">{{ $source['label'] }}</a>@endforeach)</span>@endif @else{{ $label }}@endif @empty{{ __('all healthy') }}@endforelse</span>@if($lastSync)<span aria-hidden="true">·</span><span>{{ __('Last sync :time', ['time' => $lastSync->translatedFormat('j M, H:i').' UTC']) }}</span>@endif @if($attention && ($canEdit ?? false))<a class="text-link" href="{{ $settingsUrl }}">{{ __('Review in settings') }}</a>@endif</p>
        @endif
    </div>
    <div class="project-actions">
        <nav class="period-switch" aria-label="{{ __('Reporting period') }}"><a href="{{ route('metribay.projects.show', ['workspace'=>$workspace->slug,'project'=>$project->id,'days'=>1]) }}" @if($period->isYesterday()) aria-current="page" @endif>{{ __('Yesterday') }}</a>@foreach(\Metribay\Core\Metrics\Period::PRESETS as $option)<a href="{{ route('metribay.projects.show', ['workspace'=>$workspace->slug,'project'=>$project->id,'days'=>$option]) }}" @if($period->isPreset() && $days === $option) aria-current="page" @endif>{{ __(':days days', ['days' => $option]) }}</a>@endforeach
            <details class="range-picker" wire:ignore.self><summary @if($period->isCustom()) aria-current="page" @endif>{{ $period->isCustom() ? $period->label() : __('Dates…') }}</summary>
                <form class="range-form" method="GET" action="{{ route('metribay.projects.show', ['workspace'=>$workspace->slug,'project'=>$project->id]) }}" aria-label="{{ __('Custom range') }}">
                    <label><span>{{ __('From') }}</span><input type="date" name="from" value="{{ $rangeFrom }}" min="{{ \Metribay\Core\Metrics\Period::EARLIEST }}" max="{{ $rangeMax }}" required></label>
                    <label><span>{{ __('To') }}</span><input type="date" name="to" value="{{ $rangeTo }}" min="{{ \Metribay\Core\Metrics\Period::EARLIEST }}" max="{{ $rangeMax }}" required></label>
                    <button class="button button-secondary button-small" type="submit">{{ __('Apply') }}</button>
                    <p class="field-hint">{{ __('Up to :days days. The same number of days before is the comparison.', ['days' => \Metribay\Core\Metrics\Period::MAX_DAYS]) }}</p>
                </form>
            </details>
        </nav>
        <a class="button button-secondary" href="{{ $eventsUrl }}" title="{{ trans_choice('{0} No events: :period|{1} :count event: :period|[2,*] :count events: :period', $eventCount, ['count' => $eventCount, 'period' => $period->label()]) }}">{{ __('Events') }} <span class="button-count">{{ \Illuminate\Support\Number::format($eventCount, locale: app()->getLocale()) }}</span></a>
        @if($canEdit ?? false)<button type="button" class="button button-secondary" data-dialog-open="new-event">@include('metribay::partials/icon',['name'=>'plus']) {{ __('Add event') }}</button>@endif
        @if(($canEdit ?? false) && !$project->archived)<a class="button button-secondary" href="{{ route('metribay.sources.create',['workspace'=>$workspace->slug,'project'=>$project->id]) }}">@include('metribay::partials/icon',['name'=>'plus']) {{ __('Add data source') }}</a>@endif
        <a class="button button-secondary button-icon" href="{{ $settingsUrl }}" aria-label="{{ __('Project settings') }}" title="{{ __('Project settings') }}">@include('metribay::partials/icon',['name'=>'settings'])</a>
    </div>
</header>
@if ($project->archived)<div class="notice" role="status">{{ __('This project is archived. Its recorded history remains available.') }}</div>@endif
@if($summary)
<nav class="project-summary" aria-label="{{ __('Project summary') }}">@foreach($summaryLabels as $column => $columnLabel)@if(isset($summary[$column]))<a href="#group-{{ \Metribay\Core\Metrics\ProjectLayout::SUMMARY[$column] }}"><span class="summary-label">{{ $columnLabel }}</span>@include('metribay::partials/overview-cell', ['cell' => $summary[$column], 'column' => $column, 'days' => $days])</a>@endif @endforeach</nav>
@endif
@if($justHidden)<div class="notice notice-success chart-hidden-notice" role="status">@include('metribay::partials/icon',['name'=>'eye-off'])<div>{{ __(':chart chart hidden from this project.', ['chart' => \Metribay\Core\Metrics\ProjectLayout::chartLabel($justHidden->metric_key)]) }}</div><form method="POST" action="{{ route('metribay.charts.show',['workspace'=>$workspace->slug,'project'=>$project->id,'chart'=>$justHidden->id]) }}">@csrf @method('DELETE')<input type="hidden" name="return" value="project"><button class="button button-small button-secondary" type="submit">{{ __('Undo') }}</button></form></div>@endif
<div class="project-groups">
@forelse ($rows as $row)
@if(count($row) > 1)<div class="group-pair">@endif
@foreach($row as $group)@include('metribay::partials/project-group', ['group' => $group])@endforeach
@if(count($row) > 1)</div>@endif
@empty
@if($sources)
<div class="inline-empty">@include('metribay::partials/icon',['name'=>'eye-off'])<span>{{ __('All charts from this data source are hidden.') }}</span></div>
@else
<div class="empty-state">@include('metribay::partials/icon',['name'=>'link'])<h2>{{ __('Add your first data source') }}</h2><p>{{ __('Choose a provider, sign in with a connected account and pick what to track for :project.', ['project' => $project->name]) }}</p>@if(($canEdit ?? false) && !$project->archived)<a class="button button-primary" href="{{ route('metribay.sources.create',['workspace'=>$workspace->slug,'project'=>$project->id]) }}">@include('metribay::partials/icon',['name'=>'plus']) {{ __('Add data source') }}</a>@endif</div>
@endif
@endforelse
</div>
@if($hiddenCount > 0)<p class="hidden-charts-line">@include('metribay::partials/icon',['name'=>'eye-off'])<span>{{ trans_choice('{1} :count chart hidden|[2,*] :count charts hidden', $hiddenCount, ['count' => $hiddenCount]) }}</span><span class="description-divider">·</span><a class="text-link" href="{{ $settingsUrl }}#hidden-charts">{{ __('Show') }}</a></p>@endif
</div>
