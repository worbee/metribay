@php
    // A snapshot (score, timing, count of open issues, uptime) compares its latest value with the last one of the
    // previous period, as a difference in its own unit.
    $delta = $metric['delta'] ?? null;
    $previous = $metric['previous'] ?? null;
    $unit = $metric['unit'] ?? 'count';
@endphp
@if($delta !== null)
@php
    $polarity = \Metribay\Core\Metrics\ProjectLayout::polarity($metric['key']);
    $relative = $previous ? abs($delta) / abs($previous) : ($delta != 0 ? 1 : 0);
    $tone = $polarity === 'neutral' || $relative < 0.05 ? null : (($delta > 0) === ($polarity === 'up') ? 'good' : 'bad');
    [$number, $suffix] = match ($unit) {
        'percent' => [\Illuminate\Support\Number::format(abs($delta), 2, locale: app()->getLocale()), ' pp'],
        'score' => [\Illuminate\Support\Number::format(abs($delta), 0, locale: app()->getLocale()), null],
        default => \Metribay\Core\Metrics\ProjectLayout::format(abs($delta), $unit),
    };
@endphp
<span @class(['cell-change', 'is-good' => $tone === 'good', 'is-bad' => $tone === 'bad']) title="{{ __('Change from the last value of the previous period') }}">{{ $delta > 0 ? '+' : ($delta < 0 ? '−' : '±') }}{{ $number }}@if($suffix){{ $suffix === 'ms' || $suffix === 's' ? ' ' : '' }}{{ $suffix }}@endif<span class="sr-only"> {{ __('versus previous period') }}</span></span>
@endif
