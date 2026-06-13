<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payment Provider Configuration
    |--------------------------------------------------------------------------
    | active: oson | paynet
    */
    'provider' => env('PAYMENT_PROVIDER', 'oson'),

    'oson' => [
        'base_url'  => env('OSON_BASE_URL', 'https://api.oson.uz/v2'),
        'api_key'   => env('OSON_API_KEY'),
        'timeout'   => (int) env('OSON_TIMEOUT', 30),
        'service_id' => (int) env('OSON_SERVICE_ID', 8947),
    ],

    'paynet' => [
        'base_url'    => env('PAYNET_BASE_URL', 'https://api.paynet.uz/v1'),
        'merchant_id' => env('PAYNET_MERCHANT_ID'),
        'secret_key'  => env('PAYNET_SECRET_KEY'),
        'timeout'     => (int) env('PAYNET_TIMEOUT', 30),
    ],

    'sandbox_mode' => (bool) env('PAYMENT_SANDBOX_MODE', false),

    /*
    |--------------------------------------------------------------------------
    | Commission Rate (percent)
    |--------------------------------------------------------------------------
    */
    'commission_rate' => (float) env('COMMISSION_RATE', 3.0),

    /*
    |--------------------------------------------------------------------------
    | Retry Settings
    |--------------------------------------------------------------------------
    */
    'retry' => [
        'max_attempts' => 3,
        'delays'       => [0, 1000, 2000, 4000], // milliseconds
    ],
];
