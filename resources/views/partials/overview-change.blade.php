@if($change !== null)
@php($magnitude = abs($change))
<span @class(['cell-change', 'is-up' => $change >= 10, 'is-down' => $change <= -10]) title="{{ __('Change from the preceding :days-day period', ['days' => $days]) }}">{{ $change > 0 ? '↗' : ($change < 0 ? '↘' : '→') }}&#8239;{{ \Illuminate\Support\Number::format($magnitude, $magnitude < 10 ? 1 : 0, locale: app()->getLocale()) }}%<span class="sr-only"> {{ $change < 0 ? __('lower') : __('higher') }} {{ __('versus previous period') }}</span></span>
@endif
