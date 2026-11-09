@php
    $metric = $card['metric'];
    $sourceId = $card['source']['id'];
    $metricLabel = __($metric['label'] ?? $metric['key']);
    $statusNames = [0=>__('Paused'),1=>__('Not checked yet'),2=>__('Up'),8=>__('Seems down'),9=>__('Down')];
    $series = $metric['series'] ?? [];
    $value = $metric['value'] ?? null;
    $recorded = collect($series)->filter(fn($point) => $point['value'] !== null);
    $up = $recorded->filter(fn($point) => (int) $point['value'] === 2)->count();
    $state = fn($status) => $status === null ? 'unknown' : (in_array((int) $status, [8, 9], true) ? 'down' : ((int) $status === 2 ? 'up' : 'paused'));
    $chartId = 'chart-'.$sourceId.'-'.$metric['key'];
    $summary = __('Up on :up of :known recorded days', ['up' => $up, 'known' => $recorded->count()]);
@endphp
<section id="{{ $chartId }}" @class(['metric', 'metric-strip', 'span-'.($card['span'] ?? 6)]) aria-labelledby="{{ $chartId }}-label" wire:key="{{ $chartId }}">
    <div class="metric-head">
        <div class="metric-top"><h3 id="{{ $chartId }}-label">{{ $metricLabel }}</h3>@if(!empty($card['label']))<span class="metric-source">{{ $card['label'] }}</span>@endif @include('metribay::partials/chart-hide', ['metric' => $metric, 'sourceId' => $sourceId])</div>
        <div class="metric-value-row"><strong class="metric-value"><span class="status-dot strip-{{ $state($value) }}" aria-hidden="true"></span>{{ $value === null ? __('Unknown') : ($statusNames[(int) $value] ?? __('Unknown')) }}</strong><span class="metric-previous">{{ $summary }}</span></div>
        @php($monitor = $card['source']['current_monitor'] ?? null)
        @if(is_array($monitor) && in_array((int) $monitor['value'], [8, 9], true))
        @php($checked = !empty($monitor['checked_at']) ? \Carbon\CarbonImmutable::parse($monitor['checked_at'])->utc()->translatedFormat('j M Y, H:i').' UTC' : \Carbon\CarbonImmutable::parse($monitor['date'])->translatedFormat('j M Y'))
        <p class="metric-current">@if(!empty($monitor['incident'])){{ __('Current: :status, checked :time', ['status' => $statusNames[(int) $monitor['value']] ?? __('Unknown'), 'time' => $checked]) }}@else{{ __('Last known: :status, checked :time', ['status' => $statusNames[(int) $monitor['value']] ?? __('Unknown'), 'time' => $checked]) }}@endif</p>
        @endif
    </div>
    <div class="status-strip" role="img" aria-label="{{ $metricLabel }}: {{ $summary }}" style="--days: {{ max(1, count($series)) }}">@foreach($series as $point)<i class="strip-{{ $state($point['value']) }}" title="{{ \Carbon\CarbonImmutable::parse($point['date'])->translatedFormat('j M') }}: {{ $point['value'] === null ? __('Missing') : ($statusNames[(int) $point['value']] ?? __('Unknown')) }}"></i>@endforeach</div>
    @if($series)<div class="strip-axis" aria-hidden="true"><span>{{ \Carbon\CarbonImmutable::parse($series[0]['date'])->translatedFormat('j M') }}</span><span>{{ \Carbon\CarbonImmutable::parse(end($series)['date'])->translatedFormat('j M') }}</span></div>@endif
    <div class="metric-foot">
        <span>{{ !empty($metric['observed_date']) ? __('Observed :date', ['date' => \Carbon\CarbonImmutable::parse($metric['observed_date'])->translatedFormat('j M')]) : __('Latest observation') }}</span>
        <details class="chart-table" wire:ignore.self><summary>{{ __('Daily values') }}</summary><div class="table-scroll"><table><caption class="sr-only">{{ __(':metric daily values in the data source’s reporting timezone', ['metric' => $metricLabel]) }}</caption><thead><tr><th>{{ __('Date') }}</th><th class="number">{{ __('Value') }}</th></tr></thead><tbody>@foreach (array_reverse($series) as $point)<tr><th scope="row">{{ $point['date'] }}</th><td class="number">{{ $point['value'] === null ? '—' : ($statusNames[(int) $point['value']] ?? __('Unknown')) }}</td></tr>@endforeach</tbody></table></div></details>
    </div>
</section>
