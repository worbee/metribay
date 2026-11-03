@php
    $reports = $group['queries'];
    $blockId = 'queries-'.$group['key'];
    $locale = app()->getLocale();
    $views = ['clicked' => __('Clicked'), 'ranked' => __('Ranked'), 'opportunities' => __('Opportunities')];
    $first = $reports[0];
    // Open on clicked queries, or ranked ones when nothing was clicked.
    $defaultView = $first['clicked'] ? 'clicked' : 'ranked';
    $percent = fn (float $share): string => \Illuminate\Support\Number::format(100 * $share, maxPrecision: 100 * $share < 10 ? 1 : 0, locale: $locale).'%';
@endphp
<section class="metric query-block span-6" id="{{ $blockId }}" aria-labelledby="{{ $blockId }}-heading" data-query-block wire:key="{{ $blockId }}">
    <div class="query-head">
        <h3 id="{{ $blockId }}-heading">{{ __('Top queries') }}</h3>
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
            <span>@if($report['weekly']){{ trans_choice('{1} Weekly data: the week to :date|[2,*] Weekly data: :count weeks to :date', \Metribay\Core\Connectors\BingConnector::QUERY_WEEKS[$days], ['count' => \Metribay\Core\Connectors\BingConnector::QUERY_WEEKS[$days], 'date' => \Carbon\CarbonImmutable::parse($report['end_date'])->translatedFormat('j M Y')]) }}@else{{ $window }}@endif</span>
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
                        <td class="number">{{ \Illuminate\Support\Number::format($row['clicks'], locale: $locale) }}@include('metribay::partials/overview-change', ['change' => $row['change'], 'days' => $days, 'polarity' => 'up'])</td>
                        <td class="number">{{ \Illuminate\Support\Number::format($row['impressions'], locale: $locale) }}</td>
                        <td class="number">{{ $row['ctr'] === null ? '—' : $percent($row['ctr']) }}</td>
                        <td class="number">{{ $row['position'] === null ? '—' : \Illuminate\Support\Number::format($row['position'], 1, locale: $locale) }}@include('metribay::partials/position-change', ['change' => $row['position_change']])</td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if(count($rows) > \Metribay\Core\Services\SearchQueries::TOP)<button type="button" class="text-link query-more" data-query-more data-label-more="{{ __('Show all :count', ['count' => \Illuminate\Support\Number::format(count($rows), locale: $locale)]) }}" data-label-less="{{ __('Show top :count', ['count' => \Metribay\Core\Services\SearchQueries::TOP]) }}" aria-expanded="false">{{ __('Show all :count', ['count' => \Illuminate\Support\Number::format(count($rows), locale: $locale)]) }}</button>@endif
            @else
            <p class="query-empty">{{ $view === 'opportunities' ? __('No opportunities in this period.') : __('No queries with clicks in this period.') }}</p>
            @endif
        </div>
        @endforeach
    </div>
    @endforeach
</section>
