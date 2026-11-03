@if(\Metribay\Core\Services\UsageAnalytics::page(request()))
<button type="button" class="privacy-settings" data-cc="show-preferencesModal">{{ __('Privacy settings') }}</button>
@endif
