<?php

return [
    'account_enabled' => true,
    // Optional first-party usage measurement; no tracker request before the user's choice.
    // Unset (null): off in the self-hosted personal edition, on in Cloud and Desktop (UsageAnalytics::enabled()).
    'analytics' => [
        'enabled' => env('METRIBAY_ANALYTICS_ENABLED'),
        'script_url' => 'https://s.worbee.com/script.js',
        'website_id' => 'e2ab0564-e9ab-4a86-b4cc-12bb0c8369f5',
    ],
    'edition' => env('METRIBAY_EDITION', 'personal'),
    // Public demo with the sample portfolio (docs/DEMO.md): automatic sign-in, every change refused.
    'demo' => [
        'enabled' => (bool) env('METRIBAY_DEMO', false),
        'email' => env('METRIBAY_DEMO_EMAIL', 'demo@metribay.example'),
        'trial_url' => env('METRIBAY_DEMO_TRIAL_URL', 'https://app.metribay.com/register'),
        'source_url' => env('METRIBAY_DEMO_SOURCE_URL', 'https://github.com/worbee/metribay'),
    ],
    'marketing_url' => env('METRIBAY_MARKETING_URL', 'https://metribay.com'),
    // Self-hosted operators register their own app. Managed service configuration stays in its host.
    'google' => ['enabled' => true, 'client_id' => env('METRIBAY_GOOGLE_CLIENT_ID'), 'client_secret' => env('METRIBAY_GOOGLE_CLIENT_SECRET'), 'redirect_uri' => env('METRIBAY_GOOGLE_REDIRECT_URI')],
    'security' => ['form_action_hosts' => []],
    // Interface languages offered in the language selector. English is the fallback for missing strings.
    'locales' => [
        'en' => 'English',
        'bg' => 'Български',
        'cs' => 'Čeština',
        'de' => 'Deutsch',
        'es' => 'Español',
        'fr' => 'Français',
        'it' => 'Italiano',
        'nl' => 'Nederlands',
        'pl' => 'Polski',
        'sk' => 'Slovenčina',
        'sl' => 'Slovenščina',
        'sr' => 'Srpski',
    ],
    // Extra route names (patterns allowed) that keep unfinished data source setup, such as host sign-in round trips.
    'source_setup' => ['routes' => []],
    // Switch collection off for an installation that only shows stored data, such as the demo database (docs/DEMO_DATA.md).
    'collection' => ['enabled' => (bool) env('METRIBAY_COLLECTION_ENABLED', true), 'revision_overlap_days' => 7, 'days' => 180, 'period_days' => [7, 30, 90]],
];
