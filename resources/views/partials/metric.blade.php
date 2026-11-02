@php
    $metric = $card['metric'];
    $size = $card['size'];
    $sourceId = $card['source']['id'];
    $key = $metric['key'];
    $metricValue = $metric['value'] ?? null;
    $metricUnit = $metric['unit'] ?? 'count';
    $metricLabel = __($metric['label'] ?? $key);
    $title = ($short ?? false) && isset(\Metribay\Core\Metrics\ProjectLayout::SHORT_LABELS[$key]) ? __(\Metribay\Core\Metrics\ProjectLayout::SHORT_LABELS[$key]) : $metricLabel;
    $statusNames = [0=>__('Paused'),1=>__('Not checked yet'),2=>__('Up'),8=>__('Seems down'),9=>__('Down')];
    $series = $metric['series'] ?? [];
    $previousSeries = $size === 'n' ? [] : ($metric['previous_series'] ?? []);
    $known = (int) ($metric['coverage']['known'] ?? 0);
    $total = (int) ($metric['coverage']['total'] ?? count($series));
    $hasData = collect($series)->contains(fn($point) => ($point['value'] ?? null) !== null);
    $snapshot = (bool) ($metric['snapshot'] ?? false);
    // Today's incomplete value is drawn in the chart; the badge is for earlier incomplete dates only.
    $earlierPartial = collect(array_slice($series, 0, -1))->contains(fn($point) => !empty($point['partial']));
    $quality = \Metribay\Core\Metrics\ProjectLayout::quality($key, $metricValue);
    $qualityNames = ['good'=>__('Good'),'needs-improvement'=>__('Needs improvement'),'poor'=>__('Poor')];
    $chartEvents = $size !== 'n' ? array_values(array_filter($card['source']['events'] ?? [], fn($event) => in_array($event['date'], array_column($series, 'date'), true))) : [];
    $ariaLabel = __(':metric by reporting date. :known of :total days available. Missing dates are gaps.', ['metric' => $metricLabel, 'known' => $known, 'total' => $total]);
    $chartConfig = ['ariaLabel'=>$ariaLabel, 'label'=>$metricLabel, 'unit'=>$metricUnit, 'series'=>$series, 'previous'=>$previousSeries, 'size'=>$size, 'events'=>$chartEvents];
    $chartId = 'chart-'.$sourceId.'-'.$key;
    $formatted = $metricValue !== null && !in_array($metricUnit, ['count', 'lines', 'status'], true) ? \Metribay\Core\Metrics\ProjectLayout::format((float) $metricValue, $metricUnit) : null;
    $previousTotal = $size === 'l' && !$snapshot ? ($metric['previous'] ?? null) : null;
@endphp
<section @class(['metric', 'metric-'.$size, 'span-'.($card['span'] ?? 'auto'), 'metric-solo' => $card['solo'] ?? false]) @if(($card['rows'] ?? 1) > 1) style="--rows: {{ $card['rows'] }}" @endif aria-labelledby="{{ $chartId }}-label" wire:key="{{ $chartId }}">
    <div class="metric-head">
        <div class="metric-top"><h3 id="{{ $chartId }}-label" @if($title !== $metricLabel) aria-label="{{ $metricLabel }}" @endif>{{ $title }}</h3>@if(!empty($card['label']))<span class="metric-source">{{ $card['label'] }}</span>@endif @if($earlierPartial)<span class="partial-label" title="{{ __('Some dates or the current reporting day are incomplete') }}">{{ __('Partial') }}</span>@endif @include('metribay::partials/chart-hide', ['metric' => $metric, 'sourceId' => $sourceId])</div>
        <div class="metric-value-row">
            <strong class="metric-value">@if ($metricValue === null)<span class="missing-value" aria-label="{{ __('No period total available') }}">—</span>@elseif ($metricUnit === 'status'){{ $statusNames[(int)$metricValue] ?? __('Unknown') }}@elseif($formatted){{ $formatted[0] }}@if($formatted[1])<small>{{ $formatted[1] }}</small>@endif @else<span data-metric-number="{{ $metricValue }}" data-unit="{{ $metricUnit }}">{{ \Illuminate\Support\Number::format((float)$metricValue, fmod((float)$metricValue, 1) !== 0.0 ? 1 : 0, locale: app()->getLocale()) }}</span>@endif</strong>
            @if($quality)<span class="quality quality-{{ $quality }}">{{ $qualityNames[$quality] }}</span>@endif
            @if($metricUnit !== 'status')@include('metribay::partials/overview-change', ['change' => $metric['change'] ?? null, 'days' => $days, 'polarity' => \Metribay\Core\Metrics\ProjectLayout::polarity($key)])@endif
        </div>
        @if($previousTotal !== null)<p class="metric-previous">{{ __(':value in the previous :days days', ['value' => \Illuminate\Support\Number::format((float) $previousTotal, fmod((float) $previousTotal, 1) !== 0.0 ? 1 : 0, locale: app()->getLocale()), 'days' => $days]) }}</p>
        @elseif($metricValue === null && $hasData)<p class="metric-previous">{{ __('Daily data available · period total unavailable') }}</p>@endif
    </div>
    @if ($hasData)
        <div class="chart-wrap" data-chart-container data-chart-config="{{ json_encode($chartConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) }}">
            <canvas data-metric-chart wire:ignore role="img" aria-label="{{ $ariaLabel }}" @if($size !== 'n') aria-describedby="{{ $chartId }}-help" @endif></canvas>
            @if($size !== 'n')<p class="sr-only" id="{{ $chartId }}-help">{{ __('Use the left and right arrow keys to inspect recorded dates. Daily values are also available in the table below.') }}</p><p class="sr-only" aria-live="polite" data-chart-announcement></p>@endif
        </div>
    @else
        <div class="chart-empty"><span>{{ __('No data for this period') }}</span></div>
    @endif
    <div class="metric-foot">
        @if($snapshot && empty($observedInCaption))<span>{{ !empty($metric['observed_date']) ? __('Observed :date', ['date' => \Carbon\CarbonImmutable::parse($metric['observed_date'])->translatedFormat('j M')]) : __('Latest observation') }}</span>
        @elseif(!$snapshot && $known < $total)<span class="coverage-gap">{{ __(':known/:total days recorded', ['known' => $known, 'total' => $total]) }}</span>@endif
        @if(!empty($metric['context']['collection_start']))<span>{{ __('Collection window: :start – :end', ['start' => $metric['context']['collection_start'], 'end' => $metric['context']['collection_end']]) }}</span>@endif
        @if($key === 'open_issues')<span>{{ __('Daily snapshots · no historical backfill') }}</span>@endif
        @if($hasData)
        <details class="chart-table" wire:ignore.self><summary>{{ __('Daily values') }}</summary><div class="table-scroll"><table><caption class="sr-only">{{ __(':metric daily values in the data source’s reporting timezone', ['metric' => $metricLabel]) }}</caption><thead><tr><th>{{ __('Date') }}</th><th class="number">{{ __('Value') }}</th><th>{{ __('Coverage') }}</th></tr></thead><tbody>@foreach (array_reverse($series) as $point)<tr><th scope="row">{{ $point['date'] }}@if(!empty($point['context']['collection_start']))<small class="field-hint"> · {{ $point['context']['collection_start'] }} – {{ $point['context']['collection_end'] }}</small>@endif</th><td class="number">@if($point['value'] === null)—@elseif($metricUnit === 'status'){{ $statusNames[(int) $point['value']] ?? __('Unknown') }}@else{{ \Illuminate\Support\Number::format((float)$point['value'], in_array($metricUnit, ['percent', 'ratio']) ? 3 : (fmod((float)$point['value'], 1) !== 0.0 ? 2 : 0), locale: app()->getLocale()) }}@if($metricUnit === 'percent')%@elseif($metricUnit === 'ms') ms @elseif($metricUnit === 'score')/100 @endif @endif</td><td>{{ $point['value'] === null ? __('Missing') : (!empty($point['partial']) ? __('Partial') : __('Recorded')) }}</td></tr>@endforeach</tbody></table></div></details>
        @endif
    </div>
</section>
