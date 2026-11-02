{{-- Deployment environments field for GitHub or Azure DevOps. Expects $kind, $fieldId, $value, and optional $found (list of names) and $foundError. --}}
<div class="field">
    <label for="{{ $fieldId }}">{{ __('Deployment environments') }} <span class="optional">{{ __('optional') }}</span></label>
    <input id="{{ $fieldId }}" name="environments" value="{{ $value }}" maxlength="3000" placeholder="production" autocomplete="off">
    @if($kind === 'azure_devops')<p class="field-hint">{{ __('Successful Azure DevOps deployments to these pipeline environments appear as events on the charts. Separate names with commas; leave empty for none.') }}</p>
    @else<p class="field-hint">{{ __('Successful GitHub deployments to these environments appear as events on the charts. Separate names with commas; leave empty for none.') }}</p>@endif
    @if(!empty($foundError))<p class="notice" role="alert">{{ $foundError }}</p>
    @elseif(isset($found) && $kind === 'azure_devops')<p class="field-hint">{{ $found === [] ? __('This project has no pipeline environments yet.') : __('This project’s environments: :environments.', ['environments' => implode(', ', $found)]) }}</p>
    @elseif(isset($found))<p class="field-hint">{{ $found === [] ? __('No deployments found in this repository yet.') : __('Recent deployments went to: :environments.', ['environments' => implode(', ', $found)]) }}</p>@endif
</div>
