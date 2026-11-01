<div class="overview" wire:poll.15s>
    @php
        $visibleProjects = collect($projects);
        $sourceList = $visibleProjects->flatMap(fn($item) => $item['sources'] ?? []);
        $attentionCount = $sourceList->filter(fn($source) => in_array($source['status'], ['failed','delayed']))->count();
        $activeSources = $sourceList->filter(fn($source) => $source['enabled'] ?? false)->count();
        $columnLabels = ['traffic'=>__('Visits'),'search'=>__('Search clicks'),'development'=>__('Commits · PRs'),'issues'=>__('Open issues'),'downloads'=>__('Downloads'),'uptime'=>__('Uptime · 24h')];
        $columns = array_values(array_filter(array_keys(\Metribay\Core\Services\DashboardData::CATEGORIES), fn ($column) => $visibleProjects->contains(fn ($project) => isset($project['summary'][$column]))));
        $statusLabels = ['healthy'=>__('Up to date'),'delayed'=>__('Delayed'),'failed'=>__('Needs attention'),'pending'=>__('Awaiting first sync'),'disabled'=>__('Paused'),'empty'=>__('No data sources yet.')];
        $totals = [];
        foreach ($columns as $column) {
            $cells = $visibleProjects->map(fn ($project) => $project['summary'][$column] ?? null)->filter();
            if ($column === 'uptime') {
                $totals[$column] = ['up' => $cells->sum('up'), 'total' => $cells->sum('total'), 'down' => $cells->sum('down')];
                continue;
            }
            $reported = $cells->filter(fn ($cell) => $cell['value'] !== null);
            $previous = $reported->isNotEmpty() && $reported->every(fn ($cell) => $cell['previous'] !== null) ? $reported->sum('previous') : null;
            $value = $reported->isNotEmpty() ? $reported->sum('value') : null;
            $totals[$column] = ['value' => $value, 'secondary' => $column === 'development' ? $reported->sum('secondary') : null, 'change' => ! \Metribay\Core\Metrics\MetricRegistry::get(\Metribay\Core\Services\DashboardData::CATEGORIES[$column])->snapshot && $value !== null && $previous > 0 ? 100 * ($value - $previous) / $previous : null, 'unit' => $cells->first()['unit'] ?? 'count'];
        }
    @endphp
    <header class="page-heading">
        <div>@if(\Illuminate\Support\Facades\Route::has('metribay.workspaces'))<div class="eyebrow">{{ $workspace->name }}</div>@endif<h1>{{ __('Project overview') }}<span class="heading-dot">.</span></h1><p class="page-description">{{ __('The last :days days, compared with the preceding :days.', ['days' => $days]) }}</p></div>
        <div class="heading-actions"><label class="sr-only" for="overview-period">{{ __('Reporting period') }}</label><select class="period-select" id="overview-period" wire:model.live="days">@foreach([7, 30, 90] as $option)<option value="{{ $option }}">{{ __('Last :days days', ['days' => $option]) }}</option>@endforeach</select>@if($canEdit ?? false)<a class="button button-primary" href="{{ route('metribay.projects.create', $workspace->slug) }}">@include('metribay::partials/icon', ['name'=>'plus']) {{ __('Add project') }}</a>@endif</div>
    </header>
    @if(($canEdit ?? false) && $activeSources && !collect($projects)->flatMap(fn($project) => $project['sources'])->contains(fn($source) => $source['last_success_at']))
    <section class="setup-checklist" aria-label="{{ __('Dashboard setup') }}"><h2>{{ __('Your first dashboard') }}</h2><ol><li>✓ {{ __('Project created and data source added') }}</li><li>{{ $attentionCount ? __('First import needs attention — review the data source below') : __('First import queued or running') }}</li><li>{{ __('View your metrics when the import finishes') }}</li></ol><p class="field-hint">{{ __('You can leave this page. Importing continues in the background.') }}</p></section>
    @endif
    @if(count($projects))
    <div class="overview-summary"><div class="summary-count">{!! trans_choice('{1} <strong>:count</strong> project|[2,*] <strong>:count</strong> projects', $visibleProjects->count(), ['count' => $visibleProjects->count()]) !!}<span class="summary-separator">/</span>{!! trans_choice('{0} <strong>:count</strong> active data sources|{1} <strong>:count</strong> active data source|[2,*] <strong>:count</strong> active data sources', $activeSources, ['count' => $activeSources]) !!}</div><div class="overview-freshness">@if($attentionCount)<span class="attention-dot" aria-hidden="true"></span>{{ trans_choice('{1} :count data source needs attention|[2,*] :count data sources need attention', $attentionCount, ['count' => $attentionCount]) }} @else<span @class(['status-dot','dot-neutral'=>!$activeSources]) aria-hidden="true"></span>{{ $activeSources ? __('Data source health looks clear') : ($sourceList->isNotEmpty() ? __('Automatic collection is paused') : __('Ready for your first data source')) }}@endif <span class="refresh-state" wire:loading>@include('metribay::partials/icon', ['name'=>'refresh']) {{ __('Updating') }}</span></div></div>
    <div class="overview-toolbar"><div class="filter-search">@include('metribay::partials/icon', ['name'=>'eye'])<label class="sr-only" for="project-search">{{ __('Find a project') }}</label><input id="project-search" type="search" placeholder="{{ __('Find a project…') }}" data-project-search autocomplete="off"></div><div class="toolbar-note">{{ __('Dates follow each data source’s timezone') }}</div></div>
    @if($visibleProjects->count() > 1 && $totals)
    <dl class="overview-totals" aria-label="{{ __('Totals across all projects') }}">
        @foreach($totals as $column => $total)
        <div class="overview-total"><dt>{{ $column === 'uptime' ? __('Monitors up') : $columnLabels[$column] }}</dt><dd>
            @if($column === 'uptime')<strong @class(['uptime-down' => $total['down']])>{{ $total['up'] }}/{{ $total['total'] }}</strong>
            @elseif($total['value'] === null)<span class="missing-value">—</span>
            @else<strong data-metric-number="{{ $total['value'] }}" data-unit="{{ $total['unit'] }}">{{ \Illuminate\Support\Number::format($total['value'], 0, locale: app()->getLocale()) }}</strong>@if($total['secondary'] !== null)<span class="cell-secondary"> · {{ \Illuminate\Support\Number::format($total['secondary'], 0, locale: app()->getLocale()) }}</span>@endif @include('metribay::partials/overview-change', ['change' => $total['change'], 'days' => $days])@endif
        </dd></div>
        @endforeach
    </dl>
    @endif
    @endif
    <div class="project-index" wire:loading.class="is-updating">
    @if(count($projects))
    <div class="overview-table-wrap">
    <table class="overview-table">
        <caption class="sr-only">{{ __('Projects with their headline metrics for the last :days days', ['days' => $days]) }}</caption>
        <thead><tr>
            <th scope="col" class="project-column" @if($sort === 'name') aria-sort="{{ $direction === 'asc' ? 'ascending' : 'descending' }}" @endif><button type="button" class="sort-button" wire:click="sortBy('name')">{{ __('Project') }}<span class="sort-indicator" aria-hidden="true">{{ $sort === 'name' ? ($direction === 'asc' ? '↑' : '↓') : '' }}</span></button></th>
            @forelse($columns as $column)
            <th scope="col" @if($sort === $column) aria-sort="{{ $direction === 'asc' ? 'ascending' : 'descending' }}" @endif><button type="button" class="sort-button" wire:click="sortBy('{{ $column }}')">{{ $columnLabels[$column] }}<span class="sort-indicator" aria-hidden="true">{{ $sort === $column ? ($direction === 'asc' ? '↑' : '↓') : '' }}</span></button></th>
            @empty
            <th scope="col">{{ __('Data sources') }}</th>
            @endforelse
        </tr></thead>
        <tbody>
        @foreach ($projects as $item)
        @php
            $projectUrl = route('metribay.projects.show', ['workspace'=>$workspace->slug,'project'=>$item['id'],'days'=>$days]);
            $attention = collect($item['sources'])->whereIn('status', ['failed','delayed'])->count();
        @endphp
        <tr @class(['project-row', 'needs-attention' => $attention]) data-project-name="{{ mb_strtolower($item['name'].' '.($item['domain'] ?? '')) }}" data-row-href="{{ $projectUrl }}" wire:key="project-{{ $item['id'] }}">
            <th scope="row" class="project-cell">
                <span class="project-dot source-status status-{{ $item['status'] === 'empty' ? 'pending' : $item['status'] }}" title="{{ $statusLabels[$item['status']] }}"><span class="status-dot" aria-hidden="true"></span><span class="sr-only">{{ $statusLabels[$item['status']] }}</span></span>
                <span class="project-cell-text">
                    <a class="project-link" href="{{ $projectUrl }}">{{ $item['name'] }}</a>
                    <span class="project-meta">@if($attention)@php($attentionText = trans_choice('{1} :count data source needs attention|[2,*] :count data sources need attention', $attention, ['count' => $attention]))<span class="project-attention" title="{{ $attentionText }}">{{ $attentionText }}</span>@else<span class="project-domain">{{ $item['domain'] ?: __('No domain') }}</span>@endif<span class="project-details-link" aria-hidden="true">{{ __('View project details') }}</span></span>
                </span>
            </th>
            @if(!count($item['sources']))
            <td class="row-empty" colspan="{{ max(1, count($columns)) }}"><span>{{ trans_choice('{0} No data sources|{1} :count data source|[2,*] :count data sources', 0) }}</span>@if($canEdit ?? false)<a class="text-link" href="{{ route('metribay.sources.create',['workspace'=>$workspace->slug,'project'=>$item['id']]) }}">{{ __('Add data source') }}</a>@endif</td>
            @else
            @foreach($columns as $column)
            <td data-label="{{ $columnLabels[$column] }}">@include('metribay::partials/overview-cell', ['cell' => $item['summary'][$column] ?? null, 'column' => $column, 'days' => $days])</td>
            @endforeach
            @endif
        </tr>
        @endforeach
        </tbody>
    </table>
    </div>
    @else
        <div class="empty-state">@include('metribay::partials/icon',['name'=>'chart'])<span class="eyebrow">{{ __('A clear place to start') }}</span><h2>{{ __('Create your first project') }}</h2><p>{{ __('A project brings a website, app or product’s analytics, search, development and uptime data into one view.') }}</p>@if($canEdit ?? false)<a class="button button-primary" href="{{ route('metribay.projects.create', $workspace->slug) }}">@include('metribay::partials/icon',['name'=>'plus']) {{ __('Create your first project') }}</a>@endif</div>
    @endif
    </div>
    <div class="search-empty empty-state" data-search-empty hidden><h2>{{ __('No matching projects') }}</h2><p>{{ __('Try another project name or domain.') }}</p></div>
    @if(count($projects))<div class="data-note">@include('metribay::partials/icon',['name'=>'info'])<p>{{ __('Each column adds up a project’s data sources of that kind; visits are added, unique visitors never are. A dot means none is connected, a dash means unavailable, not zero. Gaps show missing daily data.') }}</p></div>@endif
</div>
