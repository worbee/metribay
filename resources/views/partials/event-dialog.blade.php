@php
    // Several event forms share a page; old input belongs only to the form that was sent.
    $sent = old('_dialog');
    $useOld = $sent === $dialogId || ($sent === null && $eventForm === null);
@endphp
<dialog class="app-dialog" id="{{ $dialogId }}" @if($sent === $dialogId && $errors->any()) data-open-on-load @endif>
    <div class="dialog-heading"><div><span class="eyebrow">{{ $project->name }}</span><h2>{{ $eventForm ? __('Edit event') : __('Add event') }}</h2></div><button type="button" class="icon-button" data-dialog-close aria-label="{{ __('Close dialog') }}">@include('metribay::partials/icon',['name'=>'close'])</button></div>
    @if($eventForm?->recurrence)<p class="dialog-note">@include('metribay::partials/icon',['name'=>'repeat']){{ __('Changes apply to every repeat of this event.') }}</p>@endif
    <form class="form-stack" method="POST" action="{{ $eventForm ? route('metribay.events.update',['workspace'=>$workspace->slug,'project'=>$project->id,'event'=>$eventForm->id]) : route('metribay.events.store',['workspace'=>$workspace->slug,'project'=>$project->id]) }}">@csrf @if($eventForm) @method('PATCH') @endif<input type="hidden" name="_dialog" value="{{ $dialogId }}">
        @include('metribay::partials/event-fields', ['eventForm' => $eventForm, 'formPrefix' => $dialogId, 'useOld' => $useOld])
        <div class="form-actions"><button type="button" class="button button-secondary" data-dialog-close>{{ __('Cancel') }}</button><button class="button button-primary" type="submit">{{ $eventForm ? __('Save event') : __('Add event') }}</button></div>
    </form>
    @if($eventForm)<form class="delete-form dialog-delete" method="POST" action="{{ route('metribay.events.destroy',['workspace'=>$workspace->slug,'project'=>$project->id,'event'=>$eventForm->id]) }}" data-confirm="{{ $eventForm->recurrence ? __('Delete this event and all its repeats?') : __('Delete this event?') }}">@csrf @method('DELETE')<button type="submit" class="button button-danger-quiet button-small">{{ __('Delete event') }}</button></form>@endif
</dialog>
