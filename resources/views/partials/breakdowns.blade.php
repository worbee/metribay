@php
    // One breakdown block (Acquisition, Locations and devices, or Goals): a tab per dimension, a panel per data source.
    // It reuses the Top queries behaviour (data-query-*): the chosen source, tab and expanded lists survive the hourly refresh.
    $blockKey = $block['key'];
    $blockId = 'breakdown-'.$blockKey;
    $blockTitle = __(\Metribay\Core\Services\AudienceBreakdowns::BLOCK_LABELS[$blockKey]);
    $locale = app()->getLocale();
    $top = \Metribay\Core\Services\AudienceBreakdowns::TOP;
    $dimensions = $block['dimensions'];
    $reports = $block['reports'];
    $firstReports = reset($reports);
    $defaultView = collect($dimensions)->first(fn ($dimension) => isset($firstReports[$dimension])) ?? $dimensions[0];
    $sourcesById = collect($group['sources'])->keyBy('id');
    // The provider names the source; two sources of one provider add their labels.
    $kinds = collect(array_keys($reports))->map(fn ($id) => $sourcesById->get($id)['kind'])->countBy();
    $sourceNames = collect(array_keys($reports))->mapWithKeys(fn ($id) => [$id => ($providerNames[$sourcesById->get($id)['kind']] ?? $sourcesById->get($id)['kind']).($kinds[$sourcesById->get($id)['kind']] > 1 ? ' · '.$sourcesById->get($id)['label'] : '')])->all();
    $itemHeadings = ['channel' => __('Channel'), 'referrer' => __('Referrer'), 'landing_page' => __('Landing page'), 'campaign' => __('Campaign'), 'country' => __('Country'), 'device' => __('Device'), 'goal' => __('Goal')];
    $explanations = [
        'channel' => __('Where visits came from, grouped into the same channels for every analytics provider.'),
        'referrer' => __('Sites and sources that sent visits; visits without a referrer are under Direct in Channels.'),
        'landing_page' => __('The first page of each visit.'),
        'campaign' => __('Visits from links tagged with utm_campaign.'),
        'country' => __('Where visitors were, as the analytics provider located them.'),
        'device' => __('Visits by kind of device. Laptops count as desktop.'),
        'goal' => __('Goals and key events completed, as configured in the analytics provider.'),
    ];
    $percent = fn (float $share): string => \Illuminate\Support\Number::format(100 * $share, maxPrecision: 100 * $share < 10 ? 1 : 0, locale: $locale).'%';
    $date = fn (string $value, bool $year = false): string => \Carbon\CarbonImmutable::parse($value)->translatedFormat($year ? 'j M Y' : 'j M');
@endphp
<section class="metric query-block breakdown-block span-6" id="{{ $blockId }}" aria-labelledby="{{ $blockId }}-heading" data-query-block wire:key="{{ $blockId }}">
    <div class="query-head">
        <h3 id="{{ $blockId }}-heading">{{ $blockTitle }}</h3>
        @if(count($reports) > 1)
        <div class="segmented" role="group" aria-label="{{ __('Data source') }}">@foreach($sourceNames as $sourceId => $sourceName)<button type="button" data-query-source="{{ $sourceId }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">{{ $sourceName }}</button>@endforeach</div>
        @endif
        @if(count($dimensions) > 1)
        <div class="segmented" role="tablist" aria-label="{{ $blockTitle }}">@foreach($dimensions as $dimension)<button type="button" role="tab" id="{{ $blockId }}-tab-{{ $dimension }}" data-query-view="{{ $dimension }}" aria-selected="{{ $dimension === $defaultView ? 'true' : 'false' }}" aria-controls="{{ $blockId }}-{{ array_key_first($reports) }}-{{ $dimension }}" tabindex="{{ $dimension === $defaultView ? 0 : -1 }}">{{ __(\Metribay\Core\Services\AudienceBreakdowns::DIMENSION_LABELS[$dimension]) }} <span class="segmented-count" data-query-count>{{ \Illuminate\Support\Number::format(count($firstReports[$dimension]['rows'] ?? []), locale: $locale) }}</span></button>@endforeach</div>
        @endif
    </div>
    @foreach($reports as $sourceId => $byDimension)
    @php
        $source = $sourcesById->get($sourceId);
        $providerName = $providerNames[$source['kind']] ?? $source['kind'];
        $counts = array_map(fn (string $dimension): int => count($byDimension[$dimension]['rows'] ?? []), $dimensions);
    @endphp
    <div class="query-source" data-query-source-panel="{{ $sourceId }}" data-query-counts="{{ implode(',', $counts) }}" @if(!$loop->first) hidden @endif>
        @foreach($dimensions as $dimension)
        @php
            $report = $byDimension[$dimension] ?? null;
            $rows = $report['rows'] ?? [];
            $panelId = $blockId.'-'.$sourceId.'-'.$dimension;
            $measure = $report['measure'] ?? 'visits';
            $measureLabel = match (true) { $measure === 'conversions' && $source['kind'] === 'umami' => __('Events'), $measure === 'conversions' => __('Conversions'), $measure === 'visitors' => __('Visitors'), default => __('Visits') };
            $hasShare = collect($rows)->contains(fn ($row) => $row['share'] !== null);
        @endphp
        <div class="query-panel" @if(count($dimensions) > 1) role="tabpanel" aria-labelledby="{{ $blockId }}-tab-{{ $dimension }}" @endif id="{{ $panelId }}" data-query-panel="{{ $dimension }}" @if($dimension !== $defaultView) hidden @endif>
            <div class="query-caption" id="chart-{{ $sourceId }}-{{ $blockKey }}-{{ $dimension }}-label">
                <span>{{ $providerName }}@if(count($group['sources']) > 1) · {{ $source['label'] }}@endif</span>
                @if($report)
                <span aria-hidden="true">·</span>
                <span>{{ $date($report['start_date']) }} – {{ $date($report['end_date'], true) }}</span>
                @if($report['fallback'])<span class="query-coverage">{{ __('Breakdowns are stored for the last 7, 30 and 90 days; this shows the last :days days.', ['days' => $report['days']]) }}</span>@endif
                @if($report['coverage'] && $report['coverage']['known'] < $report['coverage']['total'])<span class="query-coverage">{{ __(':known / :total days recorded', $report['coverage']) }}</span>@endif
                @if($report['partial'])<span class="partial-label" title="{{ __('The latest dates are not final yet') }}">{{ __('Partial') }}</span>@endif
                @endif
                @if($loop->first)@include('metribay::partials/chart-hide', ['metric' => ['key' => $blockKey, 'label' => $blockTitle], 'sourceId' => $sourceId])@endif
            </div>
            <p class="query-explain">{{ $explanations[$dimension] }}@if($measure === 'visitors') {{ __('Counts are visitors; one visitor can appear in several rows.') }}@endif</p>
            @if($report && !empty($report['chart']))
            @php($chartConfig = ['ariaLabel' => __('Visits by channel and reporting date. Missing dates are gaps.'), 'series' => $report['chart']['series'], 'datasets' => $report['chart']['datasets']])
            <div class="channel-chart" data-channel-chart="{{ json_encode($chartConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) }}">
                <canvas role="img" aria-label="{{ $chartConfig['ariaLabel'] }}"></canvas>
            </div>
            <ul class="channel-legend" aria-hidden="true">@foreach($report['chart']['datasets'] as $dataset)<li><span class="channel-swatch channel-{{ $loop->index + 1 }}"></span>{{ $dataset['label'] }}</li>@endforeach</ul>
            @endif
            @if($rows)
            <div class="table-scroll query-scroll">
                <table class="query-table breakdown-table">
                    <caption class="sr-only">{{ count($dimensions) > 1 ? $blockTitle.' · '.__(\Metribay\Core\Services\AudienceBreakdowns::DIMENSION_LABELS[$dimension]) : $blockTitle }} · {{ $date($report['start_date']) }} – {{ $date($report['end_date'], true) }}</caption>
                    <thead><tr><th scope="col">{{ $itemHeadings[$dimension] }}</th><th scope="col" class="number">{{ $measureLabel }}</th><th scope="col" class="breakdown-share-head">@if($hasShare){{ __('Share') }}@else<span class="sr-only">{{ __('Compared with the largest') }}</span>@endif</th></tr></thead>
                    <tbody>
                    @foreach($rows as $row)
                    <tr @if($loop->index >= $top) hidden data-query-extra @endif>
                        <th scope="row" class="query-text"><div class="query-cell"><span title="{{ $row['label'] }}">{{ $row['label'] }}</span>@if(!empty($row['new']))<span class="quiet-tag">{{ __('New') }}</span>@endif</div></th>
                        <td class="number">{{ \Illuminate\Support\Number::format($row['value'], fmod((float) $row['value'], 1) !== 0.0 ? 1 : 0, locale: $locale) }}@include('metribay::partials/overview-change', ['change' => $row['change'], 'days' => $report['days'], 'polarity' => 'up', 'title' => null])</td>
                        <td class="breakdown-share"><span class="share-track" aria-hidden="true"><span class="share-bar" style="width: {{ max(1, round(100 * ($row['share'] ?? $row['bar'] ?? 0), 1)) }}%"></span></span>@if($row['share'] !== null)<span class="share-value">{{ $percent($row['share']) }}</span>@endif</td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if(count($rows) > $top)<button type="button" class="text-link query-more" data-query-more data-label-more="{{ __('Show all :count', ['count' => \Illuminate\Support\Number::format(count($rows), locale: $locale)]) }}" data-label-less="{{ __('Show top :count', ['count' => $top]) }}" aria-expanded="false">{{ __('Show all :count', ['count' => \Illuminate\Support\Number::format(count($rows), locale: $locale)]) }}</button>@endif
            @elseif($report)
            <p class="query-empty">{{ __('Nothing was recorded in this period.') }}</p>
            @else
            <p class="query-empty">{{ __(':provider has not reported this yet.', ['provider' => $providerName]) }}</p>
            @endif
        </div>
        @endforeach
    </div>
    @endforeach
</section>
