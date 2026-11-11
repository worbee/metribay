<div data-hourly-refresh data-group-scope data-viewer-id="{{ auth()->id() }}" data-workspace-id="{{ $workspace->id }}" data-project-id="{{ $project->id }}" data-group-keys="{{ implode(',', array_keys(\Metribay\Core\Metrics\ProjectLayout::GROUPS)) }}">
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
    $summaryLabels = ['traffic'=>__('Visits'),'search'=>__('Search clicks'),'development'=>__('Commits · PRs'),'issues'=>__('Open issues'),'downloads'=>__('Downloads'),'release_downloads'=>__('Release downloads'),'content'=>__('Items published'),'new_urls'=>__('New URLs detected'),'published_urls'=>__('Published URLs'),'uptime'=>__('Uptime · 24h'),'speed'=>__('Page speed · lowest')];
    $providerNames = ['ga4'=>'Google Analytics','gsc'=>'Google Search Console','bing'=>'Bing Webmaster','umami'=>'Umami Analytics','plausible'=>'Plausible','matomo'=>'Matomo','github'=>'GitHub','github_releases'=>'GitHub releases','feed'=>'RSS / Atom','sitemap'=>'Sitemap','azure_devops'=>'Azure DevOps','wporg'=>'WordPress.org','uptimerobot'=>'UptimeRobot','pagespeed'=>'PageSpeed Insights','paddle'=>'Paddle Billing'];
    $projectView = $projectView ?? 'metrics';
    $presets = $presets ?? [];
    $availableGroups = $availableGroups ?? [];
    $checkedGroups = $checkedGroups ?? [];
    $offViewSources = $offViewSources ?? [];
    $viewQuery = $viewQuery ?? ($projectView === 'overview' ? ['view' => 'overview'] : []);
    $activePreset = $activePreset ?? null;
    $matchGroups = $matchGroups ?? '';
    $visibleGroups = $visibleGroups ?? ($projectView === 'metrics' ? null : []);
    $routeParams = ['workspace' => $workspace->slug, 'project' => $project->id];
    $archive = array_filter(['queries' => $queryMonth, 'compare' => $queryCompare]);
    $metricsUrl = route('metribay.projects.show', $routeParams + $period->query() + $archive);
    $overviewUrl = route('metribay.projects.show', $routeParams + $period->query() + $archive + ['view' => 'overview']);
    $periodUrl = function (array $query) use ($routeParams, $archive, $viewQuery): string {
        return route('metribay.projects.show', $routeParams + $query + $archive + $viewQuery);
    };
    $jump = function (string $anchor, ?string $group = null) use ($projectView, $metricsUrl, $visibleGroups): string {
        $elsewhere = $projectView === 'overview' || (is_array($visibleGroups) && $group !== null && ! in_array($group, $visibleGroups, true) && ! str_starts_with($anchor, 'source-attention-'));

        return $elsewhere ? $metricsUrl.'#'.$anchor : '#'.$anchor;
    };
@endphp
<header class="project-head">
    <div class="project-head-main">
        <a class="breadcrumb" href="{{ route('metribay.dashboard', ['workspace'=>$workspace->slug, ...$period->query()]) }}">@include('metribay::partials/icon',['name'=>'arrow-left']) {{ __('All projects') }}</a>
        <div class="project-title">@if($project->domain)<img class="project-favicon" src="{{ route('metribay.projects.favicon', ['workspace'=>$workspace->slug, 'project'=>$project->id, 'v'=>substr(hash('sha256', $project->domain), 0, 12)]) }}" width="22" height="22" alt="" decoding="async">@endif<h1>{{ $project->name }}</h1>@if($domainUrl)<a class="project-domain" href="{{ $domainUrl }}" target="_blank" rel="noopener noreferrer">{{ $project->domain }} @include('metribay::partials/icon',['name'=>'arrow-up-right'])</a>@elseif($project->domain)<span class="project-domain">{{ $project->domain }}</span>@endif</div>
        @if($sources)
        <p class="project-status"><span @class(['status-dot', 'is-bad' => $attention, 'is-warn' => !$attention && ($statuses['delayed'] ?? 0) + ($statuses['pending'] ?? 0), 'dot-neutral' => count($sources) === ($statuses['disabled'] ?? 0)]) aria-hidden="true"></span><span>{{ trans_choice('{1} :count data source|[2,*] :count data sources', count($sources), ['count' => count($sources)]) }} · @forelse($states as $state => $label)@if(!$loop->first) · @endif@if(in_array($state, ['failed', 'delayed'], true))@php($affected = collect($sources)->where('status', $state)->values())<a class="text-link" href="{{ $jump($affected[0]['attention_anchor'], $affected[0]['attention_group'] ?? null) }}">{{ $label }}</a>@if($affected->count() > 1) <span class="project-attention-sources">(@foreach($affected as $source)@if(!$loop->first), @endif<a class="text-link" href="{{ $jump($source['attention_anchor'], $source['attention_group'] ?? null) }}">{{ $source['label'] }}</a>@endforeach)</span>@endif @else{{ $label }}@endif @empty{{ __('all healthy') }}@endforelse</span>@if($lastSync)<span aria-hidden="true">·</span><span>{{ __('Last sync :time', ['time' => $lastSync->translatedFormat('j M, H:i').' UTC']) }}</span>@endif @if($attention && ($canEdit ?? false))<a class="text-link" href="{{ $settingsUrl }}">{{ __('Review in settings') }}</a>@endif</p>
        @endif
    </div>
    <div class="project-actions">
        @include('metribay::partials/project-view-switch')
        <nav class="period-switch" aria-label="{{ __('Reporting period') }}"><a href="{{ $periodUrl(['days' => 1]) }}" @if($period->isYesterday()) aria-current="page" @endif>{{ __('Yesterday') }}</a>@foreach(\Metribay\Core\Metrics\Period::PRESETS as $option)<a href="{{ $periodUrl(['days' => $option]) }}" @if($period->isPreset() && $days === $option) aria-current="page" @endif>{{ __(':days days', ['days' => $option]) }}</a>@endforeach
            <details class="range-picker" wire:ignore.self><summary @if($period->isCustom()) aria-current="page" @endif>{{ $period->isCustom() ? $period->label() : __('Dates…') }}</summary>
                <form class="range-form" method="GET" action="{{ route('metribay.projects.show', $routeParams) }}" aria-label="{{ __('Custom range') }}">
                    @foreach($viewQuery as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
                    @if($queryMonth)<input type="hidden" name="queries" value="{{ $queryMonth }}">@endif
                    @if($queryCompare)<input type="hidden" name="compare" value="{{ $queryCompare }}">@endif
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
<nav class="project-summary" aria-label="{{ __('Project summary') }}">@foreach($summaryLabels as $column => $columnLabel)@if(isset($summary[$column]))<a href="{{ $jump('group-'.\Metribay\Core\Metrics\ProjectLayout::SUMMARY[$column], \Metribay\Core\Metrics\ProjectLayout::SUMMARY[$column]) }}"><span class="summary-label">{{ $columnLabel }}</span>@include('metribay::partials/overview-cell', ['cell' => $summary[$column], 'column' => $column, 'days' => $days])</a>@endif @endforeach</nav>
@endif
@if($justHidden)<div class="notice notice-success chart-hidden-notice" role="status">@include('metribay::partials/icon',['name'=>'eye-off'])<div>{{ __(':chart chart hidden from this project.', ['chart' => \Metribay\Core\Metrics\ProjectLayout::chartLabel($justHidden->metric_key)]) }}</div><form method="POST" action="{{ route('metribay.charts.show',['workspace'=>$workspace->slug,'project'=>$project->id,'chart'=>$justHidden->id]) }}">@csrf @method('DELETE')<input type="hidden" name="return" value="project"><button class="button button-small button-secondary" type="submit">{{ __('Undo') }}</button></form></div>@endif
@if($projectView === 'overview')
@foreach($sources as $source)@include('metribay::partials/source-notice', ['source' => $source, 'providerName' => $providerNames[$source['kind']] ?? $source['kind']])@endforeach
@if(is_string($projectSearchView ?? null))
@include($projectSearchView, ['searchOverview' => true])
@endif
@if($sources)
@include('metribay::partials/project-findings')
@else
<div class="empty-state">@include('metribay::partials/icon',['name'=>'link'])<h2>{{ __('Add your first data source') }}</h2><p>{{ __('Choose a provider, sign in with a connected account and pick what to track for :project.', ['project' => $project->name]) }}</p>@if(($canEdit ?? false) && !$project->archived)<a class="button button-primary" href="{{ route('metribay.sources.create',['workspace'=>$workspace->slug,'project'=>$project->id]) }}">@include('metribay::partials/icon',['name'=>'plus']) {{ __('Add data source') }}</a>@endif</div>
@endif
@else
@foreach($offViewSources as $source)@include('metribay::partials/source-notice', ['source' => $source, 'providerName' => $providerNames[$source['kind']] ?? $source['kind']])@endforeach
@if(($showProjectSearch ?? false) && is_string($projectSearchView ?? null))
@include($projectSearchView, ['searchOverview' => false])
@endif
@if($unlinkedGroups)<nav class="group-nav" aria-label="{{ __('Groups') }}">@foreach($unlinkedGroups as $group)<a href="#group-{{ $group['key'] }}">{{ __($group['title']) }}</a>@endforeach</nav>@endif
<p class="group-recovery" data-group-recovery hidden><button type="button" data-group-expand-all>{{ __('Expand all') }}</button></p>
<div class="project-groups">
@forelse ($rows as $row)
@if(count($row) > 1)<div class="group-pair">@endif
@foreach($row as $group)@include('metribay::partials/project-group', ['group' => $group])@endforeach
@if(count($row) > 1)</div>@endif
@empty
@if(($showProjectSearch ?? false) && is_string($projectSearchView ?? null) && $projectView !== 'metrics')
{{-- The host supplies the Search content above. --}}
@elseif($sources && $projectView !== 'metrics')
<div class="inline-empty"><span>{{ __('This project has no charts in this view.') }}</span></div>
@elseif($sources)
<div class="inline-empty">@include('metribay::partials/icon',['name'=>'eye-off'])<span>{{ __('All charts from this data source are hidden.') }}</span></div>
@else
<div class="empty-state">@include('metribay::partials/icon',['name'=>'link'])<h2>{{ __('Add your first data source') }}</h2><p>{{ __('Choose a provider, sign in with a connected account and pick what to track for :project.', ['project' => $project->name]) }}</p>@if(($canEdit ?? false) && !$project->archived)<a class="button button-primary" href="{{ route('metribay.sources.create',['workspace'=>$workspace->slug,'project'=>$project->id]) }}">@include('metribay::partials/icon',['name'=>'plus']) {{ __('Add data source') }}</a>@endif</div>
@endif
@endforelse
</div>
@if($hiddenCount > 0)<p class="hidden-charts-line">@include('metribay::partials/icon',['name'=>'eye-off'])<span>{{ trans_choice('{1} :count chart hidden|[2,*] :count charts hidden', $hiddenCount, ['count' => $hiddenCount]) }}</span><span class="description-divider">·</span><a class="text-link" href="{{ $settingsUrl }}#hidden-charts">{{ __('Show') }}</a></p>@endif
@endif
</div>
