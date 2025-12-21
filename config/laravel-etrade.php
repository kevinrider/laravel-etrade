<?php

return [
    'app_key' => env('ETRADE_APP_KEY', ''),
    'app_secret' => env('ETRADE_APP_SECRET', ''),
    'oauth_request_token_key' => 'laravel_etrade_oauth_request_token',
    'oauth_access_token_key' => 'laravel_etrade_oauth_access_token',
    /*
     * access tokens are good for up to 7200 seconds of inactivity
     * renew access token when inactive_buffer_in_seconds or less
     * time remains.
     */
    'inactive_buffer_in_seconds' => 1800,
    /*
    |---------------------------
    | E*Trade API environment
    |---------------------------
    | false: sandbox
    | true: prod
    */
    'production' => false,
];
