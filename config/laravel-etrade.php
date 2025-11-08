<?php

return [
    'app_key' => env('ETRADE_APP_KEY'),
    'app_secret' => env('ETRADE_APP_SECRET'),
    /*
    |--------------------------------------------------------------------------
    | E*Trade API environment
    |--------------------------------------------------------------------------
    | options: 'sandbox' or 'prod'
    | Start with the sandbox first if you are new to the API.
    */
    'env' => 'sandbox',
];
