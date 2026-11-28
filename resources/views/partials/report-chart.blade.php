@php
    // Thirteen monthly bars ending with the report month, on a zero baseline. Missing months have no bar; months with
    // missing days are drawn lighter.
    $months = $chart['months'];
    $known = array_values(array_filter(array_column($months, 'value'), fn ($value) => $value !== null));
    $high = $known ? max(max($known), 0) : 1;
    $low = $known ? min(min($known), 0) : 0;
    $range = ($high - $low) ?: 1;
    $width = 260;
    $height = 64;
    $slot = $width / count($months);
    $definition = ['unit' => \Metribay\Core\Metrics\MetricRegistry::get($chart['key'])->unit, 'currency' => $currency ?? null, 'key' => $chart['key']];
    $last = end($months);
@endphp
<figure class="report-chart">
    <figcaption>{{ __(':metric by month', ['metric' => $label]) }}</figcaption>
    <svg viewBox="0 0 {{ $width }} {{ $height }}" role="img" aria-label="{{ __(':metric by month, :from to :to. :month: :value.', ['metric' => $label, 'from' => $rules->monthLabel($months[0]['month'], 'M Y'), 'to' => $rules->monthLabel($last['month'], 'M Y'), 'month' => $rules->monthLabel($last['month'], 'M Y'), 'value' => $rules->number($definition, $last['value'])]) }}">
        <line x1="0" x2="{{ $width }}" y1="{{ round($height - 1 - (0 - $low) / $range * ($height - 2), 1) }}" y2="{{ round($height - 1 - (0 - $low) / $range * ($height - 2), 1) }}" class="report-chart-base"/>
        @foreach($months as $index => $point)
            @continue($point['value'] === null)
            @php
                $top = $height - 1 - ($point['value'] - $low) / $range * ($height - 2);
                $base = $height - 1 - (0 - $low) / $range * ($height - 2);
            @endphp
            <rect x="{{ round($index * $slot + $slot * 0.18, 1) }}" y="{{ round(min($top, $base), 1) }}" width="{{ round($slot * 0.64, 1) }}" height="{{ max(0.6, round(abs($base - $top), 1)) }}" class="{{ $loop->last ? 'is-current' : '' }}{{ $point['partial'] ? ' is-partial' : '' }}"><title>{{ $rules->monthLabel($point['month'], 'M Y') }}: {{ $rules->number($definition, $point['value']) }}</title></rect>
        @endforeach
    </svg>
    <div class="report-chart-axis" aria-hidden="true"><span>{{ $rules->monthLabel($months[0]['month'], 'M Y') }}</span><span>{{ $rules->monthLabel($last['month'], 'M Y') }}</span></div>
</figure>
