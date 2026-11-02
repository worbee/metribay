{{-- GitHub deployment environments field. Expects $fieldId, $value, and optional $found (list of names) and $foundError. --}}
<div class="field">
    <label for="{{ $fieldId }}">{{ __('Deployment environments') }} <span class="optional">{{ __('optional') }}</span></label>
    <input id="{{ $fieldId }}" name="environments" value="{{ $value }}" maxlength="3000" placeholder="production" autocomplete="off">
    <p class="field-hint">{{ __('Successful GitHub deployments to these environments appear as events on the charts. Separate names with commas; leave empty for none.') }}</p>
    @if(!empty($foundError))<p class="notice" role="alert">{{ $foundError }}</p>
    @elseif(isset($found))<p class="field-hint">{{ $found === [] ? __('No deployments found in this repository yet.') : __('Recent deployments went to: :environments.', ['environments' => implode(', ', $found)]) }}</p>@endif
</div>
