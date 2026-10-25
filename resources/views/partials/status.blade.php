@php($state = in_array($status ?? 'pending', ['healthy','delayed','failed','pending','disabled']) ? ($status ?? 'pending') : 'pending')
<span class="source-status status-{{ $state }}"><span class="status-dot" aria-hidden="true"></span>{{ ['healthy'=>'Up to date', 'delayed'=>'Delayed', 'failed'=>'Needs attention', 'pending'=>'Awaiting first sync', 'disabled'=>'Paused'][$state] }}</span>
