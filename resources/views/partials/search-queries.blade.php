@php
    $reports = $group['queries'];
    $blockId = 'queries-'.$group['key'];
    $locale = app()->getLocale();
    $views = ['clicked' => __('Clicked'), 'ranked' => __('Ranked'), 'opportunities' => __('Opportunities')];
    $first = $reports[0];
    // Open on clicked queries, or ranked ones when nothing was clicked.
    $defaultView = $first['clicked'] ? 'clicked' : 'ranked';
    $percent = fn (float $share): string => \Illuminate\Support\Number::format(100 * $share, maxPrecision: 100 * $share < 10 ? 1 : 0, locale: $locale).'%';
    $monthName = fn (string $month): string => \Carbon\CarbonImmutable::parse($month.'-01')->translatedFormat('F Y');
    // Months archived for this group's sources; the page passes the archive of all of them.
    $months = $queryMonths ?? [];
    $previousMonth = $queryMonth ? \Carbon\CarbonImmutable::parse($queryMonth.'-01')->subMonthNoOverflow()->format('Y-m') : null;
    $previousArchived = $previousMonth && in_array($previousMonth, $months, true);
    $changeTitle = $queryMonth ? ($first['compare'] ? __('Change from :month', ['month' => $monthName($first['compare'])]) : null) : null;
@endphp
<section class="metric query-block span-6" id="{{ $blockId }}" aria-labelledby="{{ $blockId }}-heading" data-query-block wire:key="{{ $blockId }}">
    <div class="query-head">
        <h3 id="{{ $blockId }}-heading">{{ __('Top queries') }}</h3>
        @if($months)
        <div class="query-period">
            <label class="sr-only" for="{{ $blockId }}-month">{{ __('Top queries period') }}</label>
            <select class="period-select" id="{{ $blockId }}-month" wire:model.live="queryMonth"><option value="">{{ __('Last :days days', ['days' => \Metribay\Core\Services\SearchQueries::storedDays($period)]) }}</option>@foreach($months as $month)<option value="{{ $month }}">{{ $monthName($month) }}</option>@endforeach</select>
            @if($queryMonth)
            <label class="sr-only" for="{{ $blockId }}-compare">{{ __('Compare with') }}</label>
            <select class="period-select" id="{{ $blockId }}-compare" wire:model.live="queryCompare"><option value="">{{ $previousArchived ? __('vs :month', ['month' => $monthName($previousMonth)]) : __('No comparison') }}</option>@foreach($months as $month)@if($month !== $queryMonth && $month !== $previousMonth)<option value="{{ $month }}">{{ __('vs :month', ['month' => $monthName($month)]) }}</option>@endif @endforeach @if($previousArchived)<option value="none">{{ __('No comparison') }}</option>@endif</select>
            @endif
        </div>
        @endif
        @if(count($reports) > 1)
        <div class="segmented" role="group" aria-label="{{ __('Data source') }}">@foreach($reports as $report)<button type="button" data-query-source="{{ $report['source']['id'] }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">{{ $providerNames[$report['source']['kind']] ?? $report['source']['kind'] }}@if(collect($reports)->where('source.kind', $report['source']['kind'])->count() > 1) · {{ $report['source']['label'] }}@endif</button>@endforeach</div>
        @endif
        <div class="segmented" role="tablist" aria-label="{{ __('Queries') }}">@foreach($views as $view => $viewLabel)<button type="button" role="tab" id="{{ $blockId }}-tab-{{ $view }}" data-query-view="{{ $view }}" aria-selected="{{ $view === $defaultView ? 'true' : 'false' }}" aria-controls="{{ $blockId }}-{{ $first['source']['id'] }}-{{ $view }}" tabindex="{{ $view === $defaultView ? 0 : -1 }}">{{ $viewLabel }} <span class="segmented-count" data-query-count>{{ \Illuminate\Support\Number::format(count($first[$view]), locale: $locale) }}</span></button>@endforeach</div>
    </div>
    @foreach($reports as $report)
    @php
        $sourceId = $report['source']['id'];
        $window = \Carbon\CarbonImmutable::parse($report['start_date'])->translatedFormat('j M').' – '.\Carbon\CarbonImmutable::parse($report['end_date'])->translatedFormat('j M Y');
        $counts = array_map(fn (string $view): int => count($report[$view]), array_keys($views));
    @endphp
    <div class="query-source" data-query-source-panel="{{ $sourceId }}" data-query-counts="{{ implode(',', $counts) }}" @if(!$loop->first) hidden @endif>
        <div class="query-caption" id="chart-{{ $sourceId }}-{{ \Metribay\Core\Services\SearchQueries::CHART_KEY }}-label">
            <span>{{ $providerNames[$report['source']['kind']] ?? $report['source']['kind'] }}@if(count($group['sources']) > 1) · {{ $report['source']['label'] }}@endif</span>
            <span aria-hidden="true">·</span>
            @if($report['month'])
            <span>@if($report['weekly'] && $report['weeks']){{ trans_choice('{1} Weekly data: :count week dated in :month|[2,*] Weekly data: :count weeks dated in :month', $report['weeks'], ['count' => $report['weeks'], 'month' => $monthName($report['month'])]) }}@else{{ $monthName($report['month']) }}@endif</span>
            @if($report['compare'])<span aria-hidden="true">·</span><span>{{ __('Compared with :month', ['month' => $monthName($report['compare'])]) }}</span>@endif
            @else
            <span>@if($report['weekly'] && $report['weeks']){{ trans_choice('{1} Weekly data: the week to :date|[2,*] Weekly data: :count weeks to :date', $report['weeks'], ['count' => $report['weeks'], 'date' => \Carbon\CarbonImmutable::parse($report['end_date'])->translatedFormat('j M Y')]) }}@else{{ $window }}@endif</span>
            @if($report['fallback'])<span class="query-coverage">{{ __('Top queries are stored for the last 7, 30 and 90 days and by month; this shows the last :days days.', ['days' => $report['days']]) }}</span>@endif
            @endif
            @if($report['partial'])<span class="partial-label" title="{{ __('The latest dates are not final yet') }}">{{ __('Partial') }}</span>@endif
            @if($report['coverage'] !== null)<span class="query-coverage">{{ __('These queries account for :share of the site’s clicks; Google leaves out rare queries.', ['share' => $percent($report['coverage'])]) }}</span>@endif
            @include('metribay::partials/chart-hide', ['metric' => ['key' => \Metribay\Core\Services\SearchQueries::CHART_KEY, 'label' => __('Top queries')], 'sourceId' => $sourceId])
        </div>
        @foreach($views as $view => $viewLabel)
        @php($rows = $report[$view])
        <div class="query-panel" role="tabpanel" id="{{ $blockId }}-{{ $sourceId }}-{{ $view }}" aria-labelledby="{{ $blockId }}-tab-{{ $view }}" data-query-panel="{{ $view }}" @if($view !== $defaultView) hidden @endif>
            <p class="query-explain">
                @if($view === 'clicked'){{ __('Queries that brought visitors, most clicks first.') }}
                @elseif($view === 'ranked'){{ __('Every query the site appeared for, most impressions first, including queries without clicks.') }}
                @else{{ __('Positions 4–20, seen at least :count times, with a click-through rate below this site’s average of :ctr. Most impressions first: better titles, descriptions or content can move these up.', ['count' => \Metribay\Core\Services\SearchQueries::OPPORTUNITY_IMPRESSIONS, 'ctr' => $report['site_ctr'] !== null ? $percent($report['site_ctr']) : '—']) }}@endif
            </p>
            @if($rows)
            <div class="table-scroll query-scroll">
                <table class="query-table">
                    <caption class="sr-only">{{ __('Top queries') }} · {{ $viewLabel }} · {{ $window }}</caption>
                    <thead><tr><th scope="col">{{ __('Query') }}</th><th scope="col" class="number">{{ __('Clicks') }}</th><th scope="col" class="number">{{ __('Impressions') }}</th><th scope="col" class="number"><abbr title="{{ __('Click-through rate') }}">{{ __('CTR') }}</abbr></th><th scope="col" class="number">{{ __('Position') }}</th></tr></thead>
                    <tbody>
                    @foreach($rows as $row)
                    <tr @if($loop->index >= \Metribay\Core\Services\SearchQueries::TOP) hidden data-query-extra @endif>
                        <th scope="row" class="query-text"><div class="query-cell"><span title="{{ $row['query'] }}">{{ $row['query'] }}</span>@if($row['new'])<span class="quiet-tag">{{ __('New') }}</span>@endif</div></th>
                        <td class="number">{{ \Illuminate\Support\Number::format($row['clicks'], locale: $locale) }}@include('metribay::partials/overview-change', ['change' => $row['change'], 'days' => $report['days'] ?? $days, 'polarity' => 'up', 'title' => $changeTitle])</td>
                        <td class="number">{{ \Illuminate\Support\Number::format($row['impressions'], locale: $locale) }}</td>
                        <td class="number">{{ $row['ctr'] === null ? '—' : $percent($row['ctr']) }}</td>
                        <td class="number">{{ $row['position'] === null ? '—' : \Illuminate\Support\Number::format($row['position'], 1, locale: $locale) }}@include('metribay::partials/position-change', ['change' => $row['position_change']])</td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if(count($rows) > \Metribay\Core\Services\SearchQueries::TOP)<button type="button" class="text-link query-more" data-query-more data-label-more="{{ __('Show all :count', ['count' => \Illuminate\Support\Number::format(count($rows), locale: $locale)]) }}" data-label-less="{{ __('Show top :count', ['count' => \Metribay\Core\Services\SearchQueries::TOP]) }}" aria-expanded="false">{{ __('Show all :count', ['count' => \Illuminate\Support\Number::format(count($rows), locale: $locale)]) }}</button>@endif
            @elseif($report['missing'])
            <p class="query-empty">{{ __('No queries were archived for :month.', ['month' => $monthName($report['month'])]) }}</p>
            @else
            <p class="query-empty">{{ $view === 'opportunities' ? __('No opportunities in this period.') : __('No queries with clicks in this period.') }}</p>
            @endif
        </div>
        @endforeach
    </div>
    @endforeach
</section>
