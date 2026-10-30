@php($scriptStrings = ['Choose a connected account','Dark','Down','Event: :title','Light','No credentials needed','Not checked yet','Partial date.','Paused','Preference saved in this browser.','Previous (:date)','Previous period','Seems down','Selected period','System','The chart could not load. Daily values remain available below.','Theme: :current. Switch to :next.','Unavailable','Up','Use a recovery code','Use an authenticator code','partial'])
@if(app()->getLocale() !== 'en')<script type="application/json" id="metribay-i18n">@json(collect($scriptStrings)->mapWithKeys(fn($text) => [$text => __($text)]), JSON_HEX_TAG | JSON_HEX_AMP)</script>@endif
<script type="module" src="{{ asset('vendor/metribay/app.js') }}?v={{ is_file(public_path('vendor/metribay/app.js')) ? filemtime(public_path('vendor/metribay/app.js')) : 'dev' }}"></script>
@livewireScripts
@stack('scripts')
