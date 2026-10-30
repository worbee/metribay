@php($languageId = 'language-'.(($compact ?? false) ? 'footer' : 'profile'))
<form @class(['language-form', 'language-form-compact' => $compact ?? false]) method="POST" action="{{ route('metribay.locale') }}">@csrf
    <label @class(['sr-only' => $compact ?? false]) for="{{ $languageId }}">{{ __('Language') }}</label>
    <select id="{{ $languageId }}" name="locale" data-auto-submit lang="{{ app()->getLocale() }}">@foreach(\Metribay\Core\Services\Locales::all() as $code => $name)<option value="{{ $code }}" lang="{{ $code }}" @selected(app()->getLocale() === $code)>{{ $name }}</option>@endforeach</select>
    <noscript><button class="button button-secondary button-small" type="submit">{{ __('Apply') }}</button></noscript>
</form>
