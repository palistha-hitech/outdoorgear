<?php

return [
    'default_api_version' => env('SHOPIFY_API_VERSION', '2025-01'),
    'isLive' => env('SHOPIFY_IS_LIVE'),
    //'isLive' => 0,
    'live' => [
        'url' => env('SHOPIFY_LIVE_URL'),
        'secret' => env('SHOPIFY_LIVE_SECRET'),
    ],
    'staging' => [
        'url' => env('SHOPIFY_STAGING_URL'),
        'secret' => env('SHOPIFY_STAGING_SECRET'),
    ],
];
