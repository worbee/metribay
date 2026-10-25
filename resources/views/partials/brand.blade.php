<a class="brand" href="{{ $brandHref ?? ($marketingUrl ?? config('metribay.marketing_url', 'https://metribay.com')) }}" aria-label="Metribay home">
    <img class="brand-icon brand-icon-light" src="{{ asset('vendor/metribay/metribay-light.svg') }}" alt="" width="40" height="40">
    <img class="brand-icon brand-icon-dark" src="{{ asset('vendor/metribay/metribay-dark.svg') }}" alt="" width="40" height="40">
    <span>metribay<span class="brand-period">.</span></span>
</a>
