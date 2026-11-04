{{-- Preference for providers that can turn collected values into events. $fieldId, $kind, $checked (bool). --}}
<input type="hidden" name="convert_to_events" value="0">
<label class="checkbox-label" for="{{ $fieldId }}"><input id="{{ $fieldId }}" type="checkbox" name="convert_to_events" value="1" @checked($checked)> {{ __('Automatically convert these values into events') }}</label>
@if($kind === 'github')<p class="field-hint">{{ __('Published releases become events on the events page. Deployments still follow the environments above.') }}</p>
@elseif($kind === 'sitemap')<p class="field-hint">{{ __('Each sitemap URL becomes an event on the events page.') }}</p>
@else<p class="field-hint">{{ __('Each feed entry becomes an event on the events page.') }}</p>@endif
