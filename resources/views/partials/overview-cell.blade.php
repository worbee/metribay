@php($breakdown = $cell ? collect($cell['parts'])->map(fn ($part) => $part['label'].': '.$part['value'])->implode("\n") : '')
@if($cell === null)
<span class="cell-none" title="{{ __('No data source of this kind') }}" aria-hidden="true">·</span><span class="sr-only">{{ __('No data source of this kind') }}</span>
@elseif($column === 'uptime')
<span @class(['overview-cell', 'uptime-down' => $cell['state'] === 'down', 'uptime-unknown' => $cell['state'] === 'unknown']) title="{{ $breakdown }}">
    @if($cell['total'])<strong class="cell-value">{{ $cell['up'] }}/{{ $cell['total'] }}</strong><span class="sr-only"> {{ __('monitors up') }}</span>@else<span class="missing-value">—</span>@endif
    @if($cell['value'] !== null)<span class="cell-secondary">{{ \Illuminate\Support\Number::format($cell['value'], $cell['value'] < 100 ? 2 : 0, locale: app()->getLocale()) }}%</span>@endif
    @include('metribay::partials/sparkline', ['series' => $cell['series'] ?? [], 'relative' => true])
</span>
@else
<span class="overview-cell" title="{{ $breakdown }}">
    @if($cell['value'] === null)<span class="missing-value" aria-label="{{ __('No period total available') }}">—</span>
    @else<strong class="cell-value" data-metric-number="{{ $cell['value'] }}" data-unit="{{ $cell['unit'] }}">{{ \Illuminate\Support\Number::format($cell['value'], 0, locale: app()->getLocale()) }}{{ $cell['unit'] === 'score' ? '/100' : '' }}</strong>@if($column === 'development' && $cell['secondary'] !== null)<span class="cell-secondary">·&#8239;{{ \Illuminate\Support\Number::format($cell['secondary'], 0, locale: app()->getLocale()) }}</span>@endif
    @include('metribay::partials/overview-change', ['change' => $cell['change'], 'days' => $days])@endif
    @include('metribay::partials/sparkline', ['series' => $cell['series']])
</span>
@endif
