@php
    $metricValue = $metric['value'] ?? null;
    $metricUnit = $metric['unit'] ?? 'count';
    $metricLabel = __($metric['label'] ?? $metric['key']);
    $statusNames = [0=>__('Paused'),1=>__('Not checked yet'),2=>__('Up'),8=>__('Seems down'),9=>__('Down')];
    $metricChange = $metric['change'] ?? null;
    $series = $metric['series'] ?? [];
    $previousSeries = $metric['previous_series'] ?? [];
    $known = (int) ($metric['coverage']['known'] ?? 0);
    $total = (int) ($metric['coverage']['total'] ?? count($series));
    $hasData = collect($series)->contains(fn($point) => ($point['value'] ?? null) !== null);
    $chartConfig = ['label'=>$metricLabel, 'unit'=>$metricUnit, 'series'=>$series, 'previous'=>$previousSeries, 'compact'=>(bool)($compact ?? false), 'events'=>($compact ?? false) ? [] : array_values(array_filter($events ?? [], fn($event) => in_array($event['date'], array_column($series, 'date'), true)))];
    $chartId = 'chart-'.($sourceId ?? 'metric').'-'.$metric['key'];
@endphp
<section @class(['metric', 'metric-compact' => $compact ?? false]) aria-labelledby="{{ $chartId }}-label">
    <div class="metric-top"><h3 id="{{ $chartId }}-label">{{ $metricLabel }}</h3>@if ($metric['partial'] ?? false)<span class="partial-label" title="{{ __('Some dates or the current reporting day are incomplete') }}">{{ __('Partial') }}</span>@endif</div>
    <div class="metric-value-row">
        <strong class="metric-value" @if(is_numeric($metricValue) && $metricUnit !== 'status') data-metric-number="{{ $metricValue }}" data-unit="{{ $metricUnit }}" @endif>
            @if ($metricValue === null)<span class="missing-value" aria-label="{{ __('No period total available') }}">—</span>
            @elseif (!empty($metric['display_value'])){{ $metric['display_value'] }}
            @elseif ($metricUnit === 'percent'){{ \Illuminate\Support\Number::format((float)$metricValue, 2, locale: app()->getLocale()) }}<small>%</small>
            @elseif ($metricUnit === 'ms'){{ \Illuminate\Support\Number::format((float)$metricValue, 0, locale: app()->getLocale()) }}<small>ms</small>
            @elseif ($metricUnit === 'status'){{ $statusNames[(int)$metricValue] ?? \Illuminate\Support\Number::format((float)$metricValue, locale: app()->getLocale()) }}
            @else{{ \Illuminate\Support\Number::format((float)$metricValue, fmod((float)$metricValue, 1) !== 0.0 ? 1 : 0, locale: app()->getLocale()) }}@endif
        </strong>
        @if ($metricChange !== null && $metricUnit !== 'status')<span class="metric-change" title="{{ __('Change from the preceding :days-day period', ['days' => $days ?? 30]) }}">@if($metricChange > 0)↗ @elseif($metricChange < 0)↘ @else→ @endif{{ $metricChange > 0 ? '+' : '' }}{{ \Illuminate\Support\Number::format((float)$metricChange, 1, locale: app()->getLocale()) }}<span>%</span><span class="sr-only"> {{ __('versus previous period') }}</span></span>@endif
    </div>
    @if ($hasData)
        <div @class(['chart-wrap', 'chart-wrap-compact'=>$compact ?? false]) data-chart-container>
            <canvas data-metric-chart data-chart-config="{{ json_encode($chartConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) }}" role="img" aria-label="{{ __(':metric by reporting date. :known of :total days available. Missing dates are gaps.', ['metric' => $metricLabel, 'known' => $known, 'total' => $total]) }}" @unless($compact ?? false) aria-describedby="{{ $chartId }}-help" @endunless></canvas>
            @unless($compact ?? false)<p class="sr-only" id="{{ $chartId }}-help">{{ __('Use the left and right arrow keys to inspect recorded dates. Daily values are also available in the table below.') }}</p><p class="sr-only" aria-live="polite" data-chart-announcement></p>@endunless
        </div>
    @else
        <div @class(['chart-empty', 'chart-empty-compact'=>$compact ?? false])><span>{{ __('No data for this period') }}</span><span class="chart-empty-line" aria-hidden="true"></span></div>
    @endif
    <div class="metric-footnote">
    @if (($metric['snapshot'] ?? false) || in_array($metric['key'], ['monitor_status','uptime_24h','response_time_24h','open_issues','indexed_pages']))
        <span>{{ !empty($metric['observed_date']) ? __('Observed :date', ['date' => $metric['observed_date']]) : __('Latest observation') }}</span>
    @elseif ($metricValue === null && $hasData)
        <span>{{ __('Daily data available · period total unavailable') }}</span>
    @else
        <span>{{ __(':known/:total days recorded', ['known' => $known, 'total' => $total]) }}</span>
    @endif
    @unless($compact ?? false)<span>{{ match($metricUnit) {'percent'=>__('Percent (%)'), 'ms'=>__('Milliseconds'), 'lines'=>__('Lines'), 'status'=>__('Provider status'), 'score'=>__('Score (0–100)'), 'ratio'=>__('Layout shift score'), default=>__('Count')} }}</span>@endunless
    </div>
    @if(!empty($metric['context']['collection_start']) && !($compact ?? false))<p class="field-hint">{{ __('Collection window: :start – :end', ['start' => $metric['context']['collection_start'], 'end' => $metric['context']['collection_end']]) }}</p>@endif
    @if($metric['key'] === 'open_issues' && !($compact ?? false))<p class="field-hint">{{ __('Daily snapshots · no historical backfill') }}</p>@endif
    @unless($compact ?? false)
    @if($hasData)
    <div class="chart-legend" aria-label="{{ __('Chart series') }}"><button type="button" data-chart-toggle="0" aria-pressed="true"><span class="legend-swatch"></span>{{ __('Selected period') }}</button>@if(collect($previousSeries)->contains(fn($point) => ($point['value'] ?? null) !== null))<button type="button" data-chart-toggle="1" aria-pressed="true"><span class="legend-swatch legend-swatch-previous"></span>{{ __('Previous period') }}</button>@endif @if(!empty($chartConfig['events']))<span class="legend-events"><span class="legend-swatch legend-swatch-event"></span>{{ trans_choice('{1} :count event|[2,*] :count events', count($chartConfig['events']), ['count' => count($chartConfig['events'])]) }}</span>@endif</div>
    <details class="chart-table"><summary>{{ __('View daily values') }}</summary><div class="table-scroll"><table><caption class="sr-only">{{ __(':metric daily values in the data source’s reporting timezone', ['metric' => $metricLabel]) }}</caption><thead><tr><th>{{ __('Date') }}</th><th class="number">{{ __('Value') }}</th><th>{{ __('Coverage') }}</th></tr></thead><tbody>@foreach ($series as $point)<tr><th scope="row">{{ $point['date'] }}@if(!empty($point['context']['collection_start']))<small class="field-hint"> · {{ $point['context']['collection_start'] }} – {{ $point['context']['collection_end'] }}</small>@endif</th><td class="number">{{ $point['value'] === null ? '—' : \Illuminate\Support\Number::format((float)$point['value'], in_array($metricUnit, ['percent', 'ratio']) ? 3 : (fmod((float)$point['value'], 1) !== 0.0 ? 2 : 0), locale: app()->getLocale()) }}@if($point['value'] !== null && $metricUnit === 'percent')%@elseif($point['value'] !== null && $metricUnit === 'ms') ms @endif</td><td>{{ $point['value'] === null ? __('Missing') : (!empty($point['partial']) ? __('Partial') : __('Recorded')) }}</td></tr>@endforeach</tbody></table></div></details>
    @endif
    @endunless
</section>
