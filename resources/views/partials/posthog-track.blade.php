{{-- What a PostHog source records: website and product usage, or the daily count of one event. --}}
@php($track = old('track', 'usage'))
<fieldset class="choice-fieldset form-stack"><legend>{{ __('What to track') }}</legend>
<label class="checkbox-label"><input type="radio" name="track" value="usage" @checked($track !== 'event')> {{ __('Website and product usage') }}</label>
<p class="field-hint">{{ __('Visitors, visits and page views in Audience, and active users in Product.') }}</p>
<label class="checkbox-label"><input type="radio" name="track" value="event" @checked($track === 'event')> {{ __('One key event') }}</label>
<div class="field"><label for="setup-event">{{ __('Event name') }}</label><input id="setup-event" name="event" maxlength="200" value="{{ old('event') }}" placeholder="user_signed_up" autocomplete="off" spellcheck="false" aria-describedby="setup-event-hint"><p class="field-hint" id="setup-event-hint">{{ __('The exact name shown in PostHog, such as a sign-up or purchase. Its daily count appears in Product. Add the project again for each event you want to follow.') }}</p></div>
</fieldset>
