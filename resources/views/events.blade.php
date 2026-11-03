@extends('metribay::layouts/app')
@section('title', __('Events').' · '.$project->name)
@section('content')
@php
    $typeNames = ['release' => __('Releases'), 'deployment' => __('Deployments'), 'custom' => __('Custom')];
    $originNames = ['github_release' => 'GitHub', 'github_deployment' => 'GitHub', 'azure_devops_deployment' => 'Azure DevOps'];
    $originTypes = ['github_release' => __('Release'), 'github_deployment' => __('Deployment'), 'azure_devops_deployment' => __('Deployment')];
    $repeatNames = ['weekly' => __('Repeats weekly'), 'monthly' => __('Repeats monthly'), 'yearly' => __('Repeats yearly')];
    $sourceLabels = collect($sources)->pluck('label', 'id');
    $base = ['workspace' => $workspace->slug, 'project' => $project->id];
    $link = fn (array $change = []): string => route('metribay.events.index', array_filter($base + ['days' => $period] + $filters + $change, fn ($value) => $value !== null && $value !== ''));
    $projectDays = in_array($period, [7, 30, 90], true) ? $period : 30;
    $filtered = array_filter($filters, fn ($value) => $value !== null && $value !== '') !== [];
    $date = fn (string $value, string $format): string => \Carbon\CarbonImmutable::parse($value)->translatedFormat($format);
    $safeUrl = fn (?string $url): bool => $url && filter_var($url, FILTER_VALIDATE_URL) && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true);
    // Custom events shown on this page get an edit dialog; a repeating event is edited once, as a series.
    $editable = collect($timeline['upcoming'])->pluck('event')->merge(collect($timeline['months'])->flatMap(fn ($month) => collect($month['days'])->flatMap(fn ($day) => collect($day['entries'])->pluck('event'))))
        ->filter(fn ($event) => $event->isCustom())->unique('id')->values();
@endphp
<header class="project-head events-head">
    <div class="project-head-main">
        <a class="breadcrumb" href="{{ route('metribay.projects.show', $base + ['days' => $projectDays]) }}">@include('metribay::partials/icon',['name'=>'arrow-left']) {{ $project->name }}</a>
        <h1>{{ __('Events') }}</h1>
        <p class="project-status"><span>{{ trans_choice('{0} No events|{1} :count event|[2,*] :count events', $timeline['count'], ['count' => $timeline['count']]) }}@if($filtered) · {{ __('filtered') }}@endif</span><span aria-hidden="true">·</span><span>{{ $date($timeline['from'], 'j M Y') }} – {{ $date($timeline['to'], 'j M Y') }}</span></p>
    </div>
    <div class="project-actions">
        <nav class="period-switch" aria-label="{{ __('Reporting period') }}">@foreach([7, 30, 90, 'all'] as $option)<a href="{{ $link(['days' => $option, 'page' => null]) }}" @if($period === $option) aria-current="page" @endif>{{ $option === 'all' ? __('All') : __(':days days', ['days' => $option]) }}</a>@endforeach</nav>
    </div>
</header>

<section class="event-context" aria-labelledby="event-context-heading">
    <h2 class="sr-only" id="event-context-heading">{{ __('Events and the main metric') }}</h2>
    @if($timeline['lanes'] || $timeline['line'])
    <div class="context-strip">
        @foreach($timeline['lanes'] as $lane)
        <div class="strip-row"><span class="strip-label">{{ $typeNames[$lane['type']] }}</span><div class="strip-track">@foreach($lane['marks'] as $mark)<a @class(['strip-mark', 'mark-dot' => $lane['type'] === 'custom', 'mark-diamond' => $lane['type'] !== 'custom', 'event-'.$mark['color'], 'is-repeat' => $mark['repeat']]) style="left: {{ $mark['x'] }}%" href="#{{ $mark['anchor'] }}" title="{{ $date($mark['date'], 'j M Y') }}: {{ implode(', ', array_slice($mark['titles'], 0, 5)) }}@if($mark['count'] > 5) … @endif"><span class="sr-only">{{ $date($mark['date'], 'j M Y') }}: {{ trans_choice('{1} :count event|[2,*] :count events', $mark['count'], ['count' => $mark['count']]) }}</span>@if($mark['count'] > 1)<span class="mark-count" aria-hidden="true">{{ $mark['count'] }}</span>@endif</a>@endforeach</div></div>
        @endforeach
        @if($timeline['line'])
        <div class="strip-row strip-line-row"><span class="strip-label">{{ $timeline['line']['label'] }}<small>{{ $timeline['line']['source'] }}</small></span><div class="strip-track"><svg viewBox="0 0 1000 60" preserveAspectRatio="none" role="img" aria-label="{{ __(':metric from :start to :end', ['metric' => $timeline['line']['label'], 'start' => $date($timeline['from'], 'j M Y'), 'end' => $date($timeline['to'], 'j M Y')]) }}"><path d="{{ $timeline['line']['path'] }}" vector-effect="non-scaling-stroke"/></svg></div></div>
        @endif
        <div class="strip-row"><span></span><div class="strip-axis"><span>{{ $date($timeline['from'], 'j M Y') }}</span><span>{{ $date($timeline['to'], 'j M Y') }}</span></div></div>
    </div>
    @if(!$timeline['lanes'])<p class="muted strip-empty">{{ __('No events in this period.') }}</p>@endif
    @endif
</section>

@if($timeline['upcoming'])
<section class="events-upcoming" aria-labelledby="upcoming-heading">
    <h2 id="upcoming-heading">{{ __('Upcoming') }}</h2>
    <ul class="upcoming-list">
    @foreach($timeline['upcoming'] as $item)
        @php($event = $item['event'])
        <li @class(['upcoming-item', 'is-ended' => $item['ended']]) id="event-{{ $event->id }}">
            <time datetime="{{ $item['date'] ?? '' }}">{{ $item['date'] ? $date($item['date'], 'j M') : '—' }}</time>
            <div class="upcoming-main">
                <p class="event-title">@include('metribay::partials/event-mark', ['event' => $event]){{ $event->title }}</p>
                <p class="event-meta">@if($event->recurrence)<span class="event-repeat">@include('metribay::partials/icon',['name'=>'repeat']){{ $repeatNames[$event->recurrence] }}</span><span aria-hidden="true">·</span>@if($item['ended']){{ __('Ended :date', ['date' => $date($event->recurrence_until?->toDateString() ?? $event->date->toDateString(), 'j M Y')]) }}@else{{ __('Next: :date', ['date' => $date($item['date'], 'j M Y')]) }}@endif @elseif($event->isCustom()){{ __('Custom event') }}@else{{ $originTypes[$event->origin] ?? $event->origin }}@endif @if($event->source_id)<span aria-hidden="true">·</span>{{ $sourceLabels[$event->source_id] ?? '' }}@endif</p>
            </div>
            @if(($canEdit ?? false) && $event->isCustom())<button type="button" class="button button-quiet button-small" data-dialog-open="edit-event-{{ $event->id }}">{{ $event->recurrence ? __('Edit series') : __('Edit') }}</button>@endif
        </li>
    @endforeach
    </ul>
</section>
@endif

<section class="event-timeline" aria-labelledby="timeline-heading">
    <header class="timeline-head">
        <h2 id="timeline-heading">{{ __('Timeline') }}</h2>
        <form class="event-filters" method="GET" action="{{ route('metribay.events.index', $base) }}" role="search">
            <input type="hidden" name="days" value="{{ $period }}">
            <label class="sr-only" for="event-type">{{ __('Type') }}</label><select id="event-type" name="type" data-auto-submit><option value="">{{ __('All types') }}</option>@foreach($typeNames as $type => $typeName)<option value="{{ $type }}" @selected(($filters['type'] ?? null) === $type)>{{ $typeName }}</option>@endforeach</select>
            @if($sources)<label class="sr-only" for="event-source">{{ __('Data source') }}</label><select id="event-source" name="source" data-auto-submit><option value="">{{ __('All data sources') }}</option><option value="project" @selected(($filters['source'] ?? null) === 'project')>{{ __('Whole project') }}</option>@foreach($sources as $sourceOption)<option value="{{ $sourceOption['id'] }}" @selected((string) ($filters['source'] ?? '') === (string) $sourceOption['id'])>{{ $sourceOption['label'] }}</option>@endforeach</select>@endif
            <div class="filter-search">@include('metribay::partials/icon',['name'=>'search'])<label class="sr-only" for="event-search">{{ __('Search titles') }}</label><input id="event-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Search titles') }}" maxlength="200"></div>
            <button class="button button-secondary button-small" type="submit">{{ __('Search') }}</button>
            @if($filtered)<a class="text-link" href="{{ route('metribay.events.index', $base + ['days' => $period]) }}">{{ __('Clear filters') }}</a>@endif
        </form>
    </header>
    @if($canEdit ?? false)
    <form class="event-quick-add" method="POST" action="{{ route('metribay.events.store', $base) }}">@csrf<input type="hidden" name="_dialog" value="quick-add">
        <label class="sr-only" for="quick-add-date">{{ __('Date') }}</label><input id="quick-add-date" type="date" name="date" value="{{ old('_dialog') === 'quick-add' ? old('date') : now()->format('Y-m-d') }}" required>
        <label class="sr-only" for="quick-add-title">{{ __('Title') }}</label><input id="quick-add-title" name="title" value="{{ old('_dialog') === 'quick-add' ? old('title') : '' }}" maxlength="180" placeholder="{{ __('What happened? e.g. Launched the new pricing page') }}" required>
        <button class="button button-primary button-small" type="submit">@include('metribay::partials/icon',['name'=>'plus']) {{ __('Add event') }}</button>
        <button type="button" class="button button-quiet button-small" data-dialog-open="new-event">{{ __('More options') }}</button>
    </form>
    @endif
    @forelse($timeline['months'] as $month)
    <section class="event-month" aria-labelledby="month-{{ $month['month'] }}">
        <h3 class="month-heading" id="month-{{ $month['month'] }}">{{ $date($month['month'].'-01', 'F Y') }}</h3>
        @foreach($month['days'] as $day)
        <div class="event-day" id="day-{{ $day['date'] }}">
            <time class="event-day-date" datetime="{{ $day['date'] }}"><strong>{{ $date($day['date'], 'j') }}</strong><span>{{ $date($day['date'], 'D') }}</span></time>
            <ol class="event-entries">
            @foreach($day['entries'] as $entry)
                @php($event = $entry['event'])
                @if(isset($entry['bundle']))
                <li class="event-entry is-imported">
                    <details class="event-bundle"><summary><p class="event-title">@include('metribay::partials/event-mark', ['event' => $event]){{ $entry['type'] === 'deployment' ? trans_choice('{1} :count deployment|[2,*] :count deployments', count($entry['bundle']), ['count' => count($entry['bundle'])]) : trans_choice('{1} :count release|[2,*] :count releases', count($entry['bundle']), ['count' => count($entry['bundle'])]) }}@if($entry['environment']) · {{ $entry['environment'] }}@endif</p><p class="event-meta">{{ __('Imported from :provider', ['provider' => $originNames[$event->origin] ?? $event->origin]) }}@if($event->source_id)<span aria-hidden="true">·</span>{{ $sourceLabels[$event->source_id] ?? '' }}@endif<span class="bundle-toggle"><span aria-hidden="true">·</span> {{ __('Show all') }}</span></p></summary>
                        <ol class="bundle-list">@foreach($entry['bundle'] as $item)<li id="event-{{ $item->id }}">@if($item->occurred_at)<time datetime="{{ $item->occurred_at->toIso8601String() }}">{{ $item->occurred_at->format('H:i') }}</time>@endif @if($safeUrl($item->url))<a class="text-link" href="{{ $item->url }}" target="_blank" rel="noopener noreferrer">{{ $item->title }}</a>@else<span>{{ $item->title }}</span>@endif</li>@endforeach</ol>
                    </details>
                </li>
                @elseif($entry['repeat'])
                <li class="event-entry is-repeat" id="occurrence-{{ $event->id }}-{{ $entry['date'] }}">
                    <p class="event-title">@include('metribay::partials/event-mark', ['event' => $event]){{ $event->title }}</p>
                    <p class="event-meta"><span class="event-repeat">@include('metribay::partials/icon',['name'=>'repeat']){{ $repeatNames[$event->recurrence] }}</span>@if($event->source_id)<span aria-hidden="true">·</span>{{ $sourceLabels[$event->source_id] ?? '' }}@endif<span aria-hidden="true">·</span><a class="text-link" href="#event-{{ $event->id }}">{{ __('Series') }}</a></p>
                </li>
                @else
                <li @class(['event-entry', 'is-imported' => !$event->isCustom()]) id="event-{{ $event->id }}">
                    <div class="event-entry-main">
                        <p class="event-title">@include('metribay::partials/event-mark', ['event' => $event])@if(!$event->isCustom() && $safeUrl($event->url))<a href="{{ $event->url }}" target="_blank" rel="noopener noreferrer">{{ $event->title }}</a>@else{{ $event->title }}@endif</p>
                        <p class="event-meta">@if($event->isCustom()){{ __('Custom event') }}@else{{ $originTypes[$event->origin] ?? $event->origin }}<span aria-hidden="true">·</span>{{ __('Imported from :provider', ['provider' => $originNames[$event->origin] ?? $event->origin]) }}@if($event->occurred_at)<span aria-hidden="true">·</span><time datetime="{{ $event->occurred_at->toIso8601String() }}">{{ $event->occurred_at->format('H:i') }} UTC</time>@endif @endif @if($event->source_id)<span aria-hidden="true">·</span>{{ $sourceLabels[$event->source_id] ?? '' }}@endif</p>
                        @if($event->body)<p class="event-body">{{ $event->body }}</p>@endif
                        @if($event->isCustom() && $safeUrl($event->url))<a class="text-link event-link" href="{{ $event->url }}" target="_blank" rel="noopener noreferrer">{{ __('Open reference') }} @include('metribay::partials/icon',['name'=>'arrow-up-right'])</a>@endif
                    </div>
                    @if(($canEdit ?? false) && $event->isCustom())<button type="button" class="button button-quiet button-small" data-dialog-open="edit-event-{{ $event->id }}">{{ __('Edit') }}</button>@endif
                </li>
                @endif
            @endforeach
            </ol>
        </div>
        @endforeach
    </section>
    @empty
    <div class="inline-empty">@include('metribay::partials/icon',['name'=>'note'])<span>{{ $filtered ? __('No events match these filters in this period.') : __('No events yet. Add one when something changes, or connect GitHub to import releases and deployments.') }}</span>@if($period !== 'all')<a class="text-link" href="{{ $link(['days' => 'all', 'page' => null]) }}">{{ __('Show all time') }}</a>@endif</div>
    @endforelse
    @if($timeline['pages'] > 1)
    <nav class="event-pages" aria-label="{{ __('Timeline pages') }}">@if($timeline['page'] > 1)<a class="button button-secondary button-small" href="{{ $link(['page' => $timeline['page'] - 1]) }}">{{ __('Newer events') }}</a>@endif<span>{{ __('Page :page of :pages', ['page' => $timeline['page'], 'pages' => $timeline['pages']]) }}</span>@if($timeline['page'] < $timeline['pages'])<a class="button button-secondary button-small" href="{{ $link(['page' => $timeline['page'] + 1]) }}">{{ __('Older events') }}</a>@endif</nav>
    @endif
</section>
@if($canEdit ?? false)
@include('metribay::partials/event-dialog', ['dialogId' => 'new-event', 'eventForm' => null])
@foreach($editable as $event)@include('metribay::partials/event-dialog', ['dialogId' => 'edit-event-'.$event->id, 'eventForm' => $event])@endforeach
@endif
@endsection
