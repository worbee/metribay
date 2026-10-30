<?php

return [
    'account_enabled' => true,
    'edition' => env('METRIBAY_EDITION', 'personal'),
    'marketing_url' => env('METRIBAY_MARKETING_URL', 'https://metribay.com'),
    // Self-hosted operators register their own app. Managed service configuration stays in its host.
    'google' => ['enabled' => true, 'client_id' => env('METRIBAY_GOOGLE_CLIENT_ID'), 'client_secret' => env('METRIBAY_GOOGLE_CLIENT_SECRET'), 'redirect_uri' => env('METRIBAY_GOOGLE_REDIRECT_URI')],
    'security' => ['form_action_hosts' => []],
    // Interface languages offered in the language selector. English is the fallback for missing strings.
    'locales' => ['en' => 'English', 'de' => 'Deutsch'],
    // Extra route names (patterns allowed) that keep unfinished data source setup, such as host sign-in round trips.
    'source_setup' => ['routes' => []],
    'collection' => ['revision_overlap_days' => 7, 'days' => 180, 'period_days' => [7, 30, 90]],
];
