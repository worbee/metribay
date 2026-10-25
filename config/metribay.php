<?php

return [
    'edition' => env('METRIBAY_EDITION', 'personal'),
    'marketing_url' => env('METRIBAY_MARKETING_URL', 'https://metribay.com'),
    'google' => ['client_id' => env('METRIBAY_GOOGLE_CLIENT_ID'), 'client_secret' => env('METRIBAY_GOOGLE_CLIENT_SECRET'), 'redirect_uri' => env('METRIBAY_GOOGLE_REDIRECT_URI')],
    'security' => ['form_action_hosts' => []],
    'collection' => ['revision_overlap_days' => 7, 'days' => 180, 'period_days' => [7, 30, 90]],
];
