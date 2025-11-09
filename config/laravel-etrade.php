<?php

return [
    'app_key' => env('ETRADE_APP_KEY'),
    'app_secret' => env('ETRADE_APP_SECRET'),
    'oauth_request_token_key' => 'laravel_etrade_oauth_request_token',
    'oauth_access_token_key' => 'laravel_etrade_oauth_access_token',
    /*
    |---------------------------
    | E*Trade API environment
    |---------------------------
    | false: sandbox
    | true: prod
    */
    'production' => false,
];
