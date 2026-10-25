@php
    $metricValue = $metric['value'] ?? null;
    $metricUnit = $metric['unit'] ?? 'count';
    $metricLabel = $metric['label'] ?? $metric['key'];
    $metricChange = $metric['change'] ?? null;
    $series = $metric['series'] ?? [];
    $previousSeries = $metric['previous_series'] ?? [];
    $known = (int) ($metric['coverage']['known'] ?? 0);
    $total = (int) ($metric['coverage']['total'] ?? count($series));
    $hasData = collect($series)->contains(fn($point) => ($point['value'] ?? null) !== null);
    $chartConfig = ['label'=>$metricLabel, 'unit'=>$metricUnit, 'series'=>$series, 'previous'=>$previousSeries, 'compact'=>(bool)($compact ?? false)];
    $chartId = 'chart-'.($sourceId ?? 'metric').'-'.$metric['key'];
@endphp
<section @class(['metric', 'metric-compact' => $compact ?? false]) aria-labelledby="{{ $chartId }}-label">
    <div class="metric-top"><h3 id="{{ $chartId }}-label">{{ $metricLabel }}</h3>@if ($metric['partial'] ?? false)<span class="partial-label" title="Some dates or the current reporting day are incomplete">Partial</span>@endif</div>
    <div class="metric-value-row">
        <strong class="metric-value" @if(is_numeric($metricValue) && $metricUnit !== 'status') data-metric-number="{{ $metricValue }}" data-unit="{{ $metricUnit }}" @endif>
            @if ($metricValue === null)<span class="missing-value" aria-label="No period total available">—</span>
            @elseif (!empty($metric['display_value'])){{ $metric['display_value'] }}
            @elseif ($metricUnit === 'percent'){{ number_format((float)$metricValue, 2) }}<small>%</small>
            @elseif ($metricUnit === 'ms'){{ number_format((float)$metricValue, 0) }}<small>ms</small>
            @elseif ($metricUnit === 'status'){{ [0=>'Paused',1=>'Not checked yet',2=>'Up',8=>'Seems down',9=>'Down'][(int)$metricValue] ?? number_format((float)$metricValue) }}
            @else{{ number_format((float)$metricValue, fmod((float)$metricValue, 1) !== 0.0 ? 1 : 0) }}@endif
        </strong>
        @if ($metricChange !== null && $metricUnit !== 'status')<span class="metric-change" title="Change from the preceding {{ $days ?? 30 }}-day period">@if($metricChange > 0)↗ @elseif($metricChange < 0)↘ @else→ @endif{{ $metricChange > 0 ? '+' : '' }}{{ number_format((float)$metricChange, 1) }}<span>%</span><span class="sr-only"> versus previous period</span></span>@endif
    </div>
    @if ($hasData)
        <div @class(['chart-wrap', 'chart-wrap-compact'=>$compact ?? false]) data-chart-container>
            <canvas data-metric-chart data-chart-config="{{ json_encode($chartConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) }}" role="img" aria-label="{{ $metricLabel }} by reporting date. {{ $known }} of {{ $total }} days available. Missing dates are gaps." @unless($compact ?? false) aria-describedby="{{ $chartId }}-help" @endunless></canvas>
            @unless($compact ?? false)<p class="sr-only" id="{{ $chartId }}-help">Use the left and right arrow keys to inspect recorded dates. Daily values are also available in the table below.</p><p class="sr-only" aria-live="polite" data-chart-announcement></p>@endunless
        </div>
    @else
        <div @class(['chart-empty', 'chart-empty-compact'=>$compact ?? false])><span>No data for this period</span><span class="chart-empty-line" aria-hidden="true"></span></div>
    @endif
    <div class="metric-footnote">
    @if (in_array($metric['key'], ['monitor_status','uptime_24h','response_time_24h','open_issues','indexed_pages']))
        <span>{{ !empty($metric['observed_date']) ? 'Observed '.$metric['observed_date'] : 'Latest observation' }}</span>
    @elseif ($metricValue === null && $hasData)
        <span>Daily data available · period total unavailable</span>
    @else
        <span>{{ $known }}/{{ $total }} days recorded</span>
    @endif
    @unless($compact ?? false)<span>{{ match($metricUnit) {'percent'=>'Percent (%)', 'ms'=>'Milliseconds', 'lines'=>'Lines', 'status'=>'Provider status', default=>'Count'} }}</span>@endunless
    </div>
    @unless($compact ?? false)
    @if($hasData)
    <div class="chart-legend" aria-label="Chart series"><button type="button" data-chart-toggle="0" aria-pressed="true"><span class="legend-swatch"></span>Selected period</button>@if(collect($previousSeries)->contains(fn($point) => ($point['value'] ?? null) !== null))<button type="button" data-chart-toggle="1" aria-pressed="true"><span class="legend-swatch legend-swatch-previous"></span>Previous period</button>@endif</div>
    <details class="chart-table"><summary>View daily values</summary><div class="table-scroll"><table><caption class="sr-only">{{ $metricLabel }} daily values in source reporting timezone</caption><thead><tr><th>Date</th><th class="number">Value</th><th>Coverage</th></tr></thead><tbody>@foreach ($series as $point)<tr><th scope="row">{{ $point['date'] }}</th><td class="number">{{ $point['value'] === null ? '—' : number_format((float)$point['value'], $metricUnit === 'percent' ? 2 : (fmod((float)$point['value'], 1) !== 0.0 ? 2 : 0)) }}@if($point['value'] !== null && $metricUnit === 'percent')%@elseif($point['value'] !== null && $metricUnit === 'ms') ms @endif</td><td>{{ $point['value'] === null ? 'Missing' : (!empty($point['partial']) ? 'Partial' : 'Recorded') }}</td></tr>@endforeach</tbody></table></div></details>
    @endif
    @endunless
</section>
