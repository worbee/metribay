@php
    // One comparison of a monthly report figure: the change, coloured when it is good or bad, and with $show the figure it is compared with.
    $other = $figure[$against] ?? null;
    $difference = $rules->difference($figure, $against);
    $prefix = ['previous' => 'previous_', 'year_ago' => 'year_'][$against] ?? null;
    $otherPartial = $prefix !== null && $other !== null && ($figure[$prefix.'partial'] ?? false);
@endphp
@if($difference !== null)<span class="report-change{{ $difference['good'] === true ? ' is-good' : ($difference['good'] === false ? ' is-bad' : '') }}">{{ $difference['text'] }}@if($difference['per_day'])<span aria-hidden="true">†</span><span class="sr-only"> {{ __('per day') }}</span>@endif</span>@elseif(! $show)<span class="report-change">—</span>@endif
@if($show)<span class="report-base{{ $difference === null ? ' is-only' : '' }}">{{ $rules->number($figure, $other === null ? null : (float) $other) }}@if($otherPartial)<span class="report-mark" aria-hidden="true">*</span><span class="sr-only"> {{ __('Part of the month') }}</span>@endif @if($against === 'trailing_average' && $other !== null && ($figure['trailing_months'] ?? 3) < 3)({{ $figure['trailing_months'] }}/3)@endif</span>@endif
