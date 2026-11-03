{{-- The same marks as on the charts: a coloured dot for custom events, a diamond for imported ones. --}}
@if($event->isCustom())<span class="event-swatch event-{{ $event->displayColor() }}" aria-hidden="true"></span>@else<span class="event-diamond" aria-hidden="true"></span>@endif
