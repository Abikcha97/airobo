<?php

return [
    'default' => [
        'host'     => env('REDIS_HOST', '127.0.0.1'),
        'port'     => (int) env('REDIS_PORT', 6379),
        'password' => env('REDIS_PASSWORD'),
        'database' => (int) env('REDIS_DB', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | TTL values (seconds)
    |--------------------------------------------------------------------------
    */
    'ttl' => [
        'otp'              => 180,   // 3 minutes
        'session'          => 3600,  // 1 hour
        'refresh_token'    => 2592000, // 30 days
        'rate_limit'       => 60,
        'circuit_breaker'  => 60,
        'circuit_half_open' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Key Prefixes
    |--------------------------------------------------------------------------
    */
    'keys' => [
        'otp'             => 'otp:',
        'token_blacklist' => 'blacklist:',
        'rate_limit'      => 'rl:',
        'circuit_breaker' => 'cb:',
    ],
];
