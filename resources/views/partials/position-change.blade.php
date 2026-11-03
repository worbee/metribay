@if($change !== null && abs($change) >= 0.05)
@php($magnitude = abs($change))
{{-- A lower position is better: a positive change means the query moved up. Colour from one whole position. --}}
<span @class(['cell-change', 'is-good' => $change >= 1, 'is-bad' => $change <= -1]) title="{{ __('Change in average position from the preceding period') }}">{{ $change > 0 ? '↗' : '↘' }}&#8239;{{ \Illuminate\Support\Number::format($magnitude, 1, locale: app()->getLocale()) }}<span class="sr-only"> {{ $change > 0 ? __('positions up') : __('positions down') }}</span></span>
@endif
