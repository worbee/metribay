@php
    // Missing days split the line instead of being drawn as zero.
    $width = 56;
    $height = 16;
    $known = array_filter($series, fn ($value) => $value !== null);
    $low = $known ? min(0, min($known)) : 0;
    $range = ($known ? max($known) : 0) - $low ?: 1;
    $count = count($series);
    $segments = [];
    $segment = [];
    foreach ($series as $index => $value) {
        if ($value === null) {
            if ($segment) $segments[] = $segment;
            $segment = [];
            continue;
        }
        $segment[] = [round($count > 1 ? $index * $width / ($count - 1) : $width / 2, 1), round($height - 1.5 - (($value - $low) / $range) * ($height - 3), 1)];
    }
    if ($segment) $segments[] = $segment;
@endphp
@if($segments)
<svg class="sparkline" viewBox="0 0 {{ $width }} {{ $height }}" width="{{ $width }}" height="{{ $height }}" aria-hidden="true" focusable="false">@foreach($segments as $points)@if(count($points) === 1)<circle cx="{{ $points[0][0] }}" cy="{{ $points[0][1] }}" r="1.3"/>@else<polyline points="{{ collect($points)->map(fn ($point) => implode(',', $point))->implode(' ') }}"/>@endif @endforeach</svg>
@endif
