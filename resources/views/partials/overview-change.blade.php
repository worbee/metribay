@if($change !== null)
@php($magnitude = abs($change))
{{-- Polarity "down" means a rise is bad (response time, failures); "neutral" is never coloured. --}}
@php($polarity = $polarity ?? 'up')
@php($tone = $polarity === 'neutral' || $magnitude < 10 ? null : (($change > 0) === ($polarity === 'up') ? 'good' : 'bad'))
<span @class(['cell-change', 'is-good' => $tone === 'good', 'is-bad' => $tone === 'bad']) title="{{ __('Change from the preceding :days-day period', ['days' => $days]) }}">{{ $change > 0 ? '↗' : ($change < 0 ? '↘' : '→') }}&#8239;{{ \Illuminate\Support\Number::format($magnitude, $magnitude < 10 ? 1 : 0, locale: app()->getLocale()) }}%<span class="sr-only"> {{ $change < 0 ? __('lower') : __('higher') }} {{ __('versus previous period') }}</span></span>
@endif
