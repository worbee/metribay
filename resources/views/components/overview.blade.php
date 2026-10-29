<div class="overview" wire:poll.15s>
    @php
        $visibleProjects = collect($projects);
        $sourceList = $visibleProjects->flatMap(fn($item) => $item['sources'] ?? []);
        $attentionCount = $sourceList->filter(fn($source) => in_array($source['status'], ['failed','delayed']))->count();
        $activeSources = $sourceList->filter(fn($source) => $source['enabled'] ?? false)->count();
    @endphp
    <header class="page-heading">
        <div>@if(\Illuminate\Support\Facades\Route::has('metribay.workspaces'))<div class="eyebrow">{{ $workspace->name }}</div>@endif<h1>Project overview<span class="heading-dot">.</span></h1><p class="page-description">The last {{ $days }} days, compared with the preceding {{ $days }}.</p></div>
        <div class="heading-actions"><label class="sr-only" for="overview-period">Reporting period</label><select class="period-select" id="overview-period" wire:model.live="days"><option value="7">Last 7 days</option><option value="30">Last 30 days</option><option value="90">Last 90 days</option></select>@if($canEdit ?? false)<a class="button button-primary" href="{{ route('metribay.sources.create', $workspace->slug) }}">@include('metribay::partials/icon', ['name'=>'plus']) Add source</a>@endif</div>
    </header>
    @if(($canEdit ?? false) && $activeSources && !collect($projects)->flatMap(fn($project) => $project['sources'])->contains(fn($source) => $source['last_success_at']))
    <section class="setup-checklist" aria-label="Dashboard setup"><h2>Your first dashboard</h2><ol><li>✓ Source connected</li><li>First import {{ $attentionCount ? 'needs attention — review the source below' : 'queued or running' }}</li><li>View your metrics when the import finishes</li></ol><p class="field-hint">You can leave this page. Importing continues in the background.</p></section>
    @endif
    @if(count($projects))
    <div class="overview-summary"><div class="summary-count"><strong>{{ $visibleProjects->count() }}</strong> {{ \Illuminate\Support\Str::plural('project', $visibleProjects->count()) }}<span class="summary-separator">/</span><strong>{{ $activeSources }}</strong> active {{ \Illuminate\Support\Str::plural('source', $activeSources) }}</div><div class="overview-freshness">@if($attentionCount)<span class="attention-dot" aria-hidden="true"></span>{{ $attentionCount }} {{ \Illuminate\Support\Str::plural('source', $attentionCount) }} need attention @else<span @class(['status-dot','dot-neutral'=>!$activeSources]) aria-hidden="true"></span>{{ $activeSources ? 'Source health looks clear' : ($sourceList->isNotEmpty() ? 'Automatic collection is paused' : 'Ready for your first source') }}@endif <span class="refresh-state" wire:loading>@include('metribay::partials/icon', ['name'=>'refresh']) Updating</span></div></div>
    <div class="overview-toolbar"><div class="filter-search">@include('metribay::partials/icon', ['name'=>'eye'])<label class="sr-only" for="project-search">Find a project</label><input id="project-search" type="search" placeholder="Find a project…" data-project-search autocomplete="off"></div><div class="toolbar-note">Dates follow each source’s timezone</div></div>
    @endif
    <div class="project-index" wire:loading.class="is-updating">
    @forelse ($projects as $item)
    <article class="project-section" data-project-name="{{ mb_strtolower($item['name'].' '.($item['domain'] ?? '')) }}" wire:key="project-{{ $item['id'] }}">
        <header class="project-header"><a class="project-title" href="{{ route('metribay.projects.show', ['workspace'=>$workspace->slug,'project'=>$item['id'],'days'=>$days]) }}"><span class="project-initial">{{ mb_strtoupper(mb_substr($item['name'],0,1)) }}</span><div><h2>{{ $item['name'] }} @include('metribay::partials/icon', ['name'=>'arrow-up-right'])</h2><span class="project-domain">{{ $item['domain'] ?: 'No domain' }}</span></div></a><div class="project-header-meta"><span class="source-count">{{ count($item['sources']) }} {{ \Illuminate\Support\Str::plural('source', count($item['sources'])) }}</span></div></header>
        @forelse ($item['sources'] as $source)
        <section class="source-overview" wire:key="source-{{ $source['id'] }}">
            <div class="source-heading"><div class="source-name"><span class="provider-icon" aria-hidden="true">{{ mb_strtoupper(mb_substr($source['kind'],0,1)) }}</span><h3>{{ $source['label'] }}</h3><span class="provider-name">{{ ['ga4'=>'Google Analytics','gsc'=>'Search Console','bing'=>'Bing Webmaster','umami'=>'Umami','github'=>'GitHub','wporg'=>'WordPress.org','uptimerobot'=>'UptimeRobot'][$source['kind']] ?? $source['kind'] }}</span></div><div class="source-freshness">@include('metribay::partials/status',['status'=>$source['status']])@if($source['last_success_at'])<time class="last-sync" title="Last successful sync">{{ $source['last_success_at'] }}</time>@endif</div></div>
            @if(in_array($source['status'],['failed','delayed']))<p class="source-attention">@include('metribay::partials/icon',['name'=>'info']){{ $source['status'] === 'failed' ? 'This source needs attention. Latest recorded data is shown below.' : 'Recent data is delayed. Existing history is shown below.' }} @if($canManageConnections ?? false)<a href="{{ route('metribay.settings',['workspace'=>$workspace->slug,'tab'=>'sources']) }}#source-{{ $source['id'] }}">Review source</a>@endif</p>@endif
            @if($source['last_success_at'])
            <div class="metrics-strip">@forelse ($source['metrics'] as $metric)@include('metribay::partials/metric', ['metric'=>$metric,'sourceId'=>$source['id'],'compact'=>true,'days'=>$days])@empty<div class="source-empty"><span>{{ ($source['enabled'] ?? false) ? 'Metrics will appear after the first successful sync.' : 'Collection is paused for this source.' }}</span></div>@endforelse</div>
            @else<p class="field-hint">{{ $source['status'] === 'failed' ? 'The first import needs attention. Review this source to retry.' : (($source['enabled'] ?? false) ? 'Waiting for the first import to finish.' : 'Collection is paused.') }}</p>@endif
        </section>
        @empty
            <div class="inline-empty">@include('metribay::partials/icon',['name'=>'link'])<span>No sources connected yet.</span>@if($canEdit ?? false)<a class="text-link" href="{{ route('metribay.sources.create',['workspace'=>$workspace->slug,'project'=>$item['id']]) }}">Connect a source @include('metribay::partials/icon',['name'=>'arrow-up-right'])</a>@endif</div>
        @endforelse
    </article>
    @empty
        <div class="empty-state">@include('metribay::partials/icon',['name'=>'chart'])<span class="eyebrow">A clear place to start</span><h2>Connect your first source</h2><p>Bring analytics, search, development or uptime data into one view.</p>@if($canEdit ?? false)<a class="button button-primary" href="{{ route('metribay.sources.create', $workspace->slug) }}">@include('metribay::partials/icon',['name'=>'plus']) Add your first source</a>@endif</div>
    @endforelse
    </div>
    <div class="search-empty empty-state" data-search-empty hidden><h2>No matching projects</h2><p>Try another project name or domain.</p></div>
    @if(count($projects))<div class="data-note">@include('metribay::partials/icon',['name'=>'info'])<p>A dash means unavailable, not zero. Unique visitors are never added across dates or projects. Gaps show missing daily data.</p></div>@endif
</div>
