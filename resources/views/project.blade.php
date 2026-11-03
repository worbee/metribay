@extends('metribay::layouts/app')
@section('title', $project->name)
@section('content')
@livewire('metribay-project-charts', ['workspaceId' => $workspace->id, 'projectId' => $project->id, 'days' => $days])
@if($canEdit ?? false)
@include('metribay::partials/event-dialog', ['dialogId' => 'new-event', 'eventForm' => null])
@endif
<div class="data-note">@include('metribay::partials/icon',['name'=>'info'])<p>{{ __('Daily charts retain missing dates as gaps. Comparisons use the preceding period. Non-additive metrics use provider period totals when available.') }}</p></div>
@endsection
