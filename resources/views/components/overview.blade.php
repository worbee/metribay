<div class="overview" wire:poll.15s>
    @php
        $visibleProjects = collect($projects);
        $sourceList = $visibleProjects->flatMap(fn($item) => $item['sources'] ?? []);
        $attentionCount = $sourceList->filter(fn($source) => in_array($source['status'], ['failed','delayed']))->count();
        $activeSources = $sourceList->filter(fn($source) => $source['enabled'] ?? false)->count();
        $providerNames = ['ga4'=>'Google Analytics','gsc'=>'Search Console','bing'=>'Bing Webmaster','umami'=>'Umami','github'=>'GitHub','wporg'=>'WordPress.org','uptimerobot'=>'UptimeRobot'];
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
    @endif
    <div class="project-index" wire:loading.class="is-updating">
    @forelse ($projects as $item)
    <article class="project-section" data-project-name="{{ mb_strtolower($item['name'].' '.($item['domain'] ?? '')) }}" wire:key="project-{{ $item['id'] }}">
        <header class="project-header"><a class="project-title" href="{{ route('metribay.projects.show', ['workspace'=>$workspace->slug,'project'=>$item['id'],'days'=>$days]) }}"><span class="project-initial">{{ mb_strtoupper(mb_substr($item['name'],0,1)) }}</span><div><h2>{{ $item['name'] }}</h2><span class="project-domain">{{ $item['domain'] ?: __('No domain') }}</span></div><span class="project-details-link">{{ __('View project details') }}</span></a><div class="project-header-meta"><span class="source-count">{{ trans_choice('{0} No data sources|{1} :count data source|[2,*] :count data sources', count($item['sources']), ['count' => count($item['sources'])]) }}</span></div></header>
        @forelse ($item['sources'] as $source)
        <section class="source-overview" wire:key="source-{{ $source['id'] }}">
            <div class="source-heading"><div class="source-name"><span class="provider-icon" aria-hidden="true">{{ mb_strtoupper(mb_substr($source['kind'],0,1)) }}</span><h3>{{ $source['label'] }}</h3><span class="provider-name">{{ $providerNames[$source['kind']] ?? $source['kind'] }}</span></div><div class="source-freshness">@include('metribay::partials/status',['status'=>$source['status']])@if($source['last_success_at'])<time class="last-sync" title="{{ __('Last successful sync') }}">{{ $source['last_success_at'] }}</time>@endif</div></div>
            @if(in_array($source['status'],['failed','delayed']))<p class="source-attention">@include('metribay::partials/icon',['name'=>'info']){{ $source['status'] === 'failed' ? __('This data source needs attention. Latest recorded data is shown below.') : __('Recent data is delayed. Existing history is shown below.') }} @if($canEdit ?? false)<a href="{{ route('metribay.projects.edit',['workspace'=>$workspace->slug,'project'=>$item['id']]) }}#source-{{ $source['id'] }}">{{ __('Review data source') }}</a>@endif</p>@endif
            @if($source['last_success_at'])
            <div class="metrics-strip">@forelse ($source['metrics'] as $metric)@include('metribay::partials/metric', ['metric'=>$metric,'sourceId'=>$source['id'],'compact'=>true,'days'=>$days])@empty<div class="source-empty"><span>{{ ($source['enabled'] ?? false) ? __('Metrics will appear after the first successful sync.') : __('Collection is paused for this data source.') }}</span></div>@endforelse</div>
            @else<p class="field-hint">{{ $source['status'] === 'failed' ? __('The first import needs attention. Review this data source to retry.') : (($source['enabled'] ?? false) ? __('Waiting for the first import to finish.') : __('Collection is paused.')) }}</p>@endif
        </section>
        @empty
            <div class="inline-empty">@include('metribay::partials/icon',['name'=>'link'])<span>{{ __('No data sources yet.') }}</span>@if($canEdit ?? false)<a class="text-link" href="{{ route('metribay.sources.create',['workspace'=>$workspace->slug,'project'=>$item['id']]) }}">{{ __('Add data source') }} @include('metribay::partials/icon',['name'=>'arrow-up-right'])</a>@endif</div>
        @endforelse
    </article>
    @empty
        <div class="empty-state">@include('metribay::partials/icon',['name'=>'chart'])<span class="eyebrow">{{ __('A clear place to start') }}</span><h2>{{ __('Create your first project') }}</h2><p>{{ __('A project brings a website, app or product’s analytics, search, development and uptime data into one view.') }}</p>@if($canEdit ?? false)<a class="button button-primary" href="{{ route('metribay.projects.create', $workspace->slug) }}">@include('metribay::partials/icon',['name'=>'plus']) {{ __('Create your first project') }}</a>@endif</div>
    @endforelse
    </div>
    <div class="search-empty empty-state" data-search-empty hidden><h2>{{ __('No matching projects') }}</h2><p>{{ __('Try another project name or domain.') }}</p></div>
    @if(count($projects))<div class="data-note">@include('metribay::partials/icon',['name'=>'info'])<p>{{ __('A dash means unavailable, not zero. Unique visitors are never added across dates or projects. Gaps show missing daily data.') }}</p></div>@endif
</div>
