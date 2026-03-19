<?php

return [ 
    'live' => [
        'url' => env('SHOPIFY_LIVE_URL'),
        'secret' => env('SHOPIFY_LIVE_SECRET'),
    ],

    'staging' => [
        'url' => env('SHOPIFY_STAGING_URL'),
        'secret' => env('SHOPIFY_STAGING_SECRET'),
    ],
    'isLive' => env('ISLIVE'),
    'clientCode' => env('CLIENTCODE','603965')


];