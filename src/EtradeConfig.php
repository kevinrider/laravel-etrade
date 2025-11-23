<?php

namespace KevinRider\LaravelEtrade;

class EtradeConfig
{
    /*
     * URLs
     */
    public const string SANDBOX_BASE_URL = 'https://apisb.etrade.com';
    public const string LIVE_BASE_URL = 'https://api.etrade.com';
    public const string AUTHORIZE_URL = 'https://us.etrade.com/e/t/etws/authorize';
    /*
     * Authorization
     */
    public const string OAUTH_REQUEST_TOKEN = 'oauth/request_token';
    public const string OAUTH_ACCESS_TOKEN = 'oauth/access_token';
    public const string OAUTH_RENEW_ACCESS_TOKEN = 'oauth/renew_access_token';
    public const string OAUTH_REVOKE_ACCESS_TOKEN = 'oauth/revoke_access_token';
    /*
     * ACCOUNTS
     */
    public const string ACCOUNTS_LIST = 'v1/accounts/list';
    public const string ACCOUNTS_BALANCE = 'v1/accounts/{accountIdKey}/balance';
    public const string ACCOUNTS_TRANSACTIONS = 'v1/accounts/{accountIdKey}/transactions';
    public const string ACCOUNTS_TRANSACTIONS_DETAILS = 'v1/accounts/{accountIdKey}/transactions/{transactionId}';
    public const string ACCOUNTS_PORTFOLIO = 'v1/accounts/{accountIdKey}/portfolio';
    /*
     * ALERTS
     */
    public const string ALERTS_LIST = 'v1/user/alerts';
    public const string ALERTS_DETAILS = 'v1/user/alerts/{alertId}';
    public const string ALERTS_DELETE = 'v1/user/alerts/{alertId}';
    /*
     * MARKET
     */
    public const string MARKET_QUOTES = 'v1/market/quote/{symbols}';
    public const string MARKET_LOOKUP = 'v1/market/lookup/{search}';
    public const string MARKET_OPTION_CHAINS = 'v1/market/optionchains';
    public const string MARKET_OPTION_EXPIRY = 'v1/market/optionexpiredate';
    /*
     * ORDER
     */
    public const string ORDER_LIST = 'v1/accounts/{accountIdKey}/orders';
    public const string ORDER_PREVIEW = 'v1/accounts/{accountIdKey}/orders/preview';
    public const string ORDER_PLACE = 'v1/accounts/{accountIdKey}/orders/place';
    public const string ORDER_CANCEL = 'v1/accounts/{accountIdKey}/orders/cancel';
    public const string ORDER_CHANGE_PREVIEW = 'v1/accounts/{accountIdKey}/orders/{orderId}/change/preview';
    public const string ORDER_PLACE_CHANGE = 'v1/accounts/{accountIdKey}/orders/{orderId}/change/place';

    /*
     * API response constants
     */
    public const string OAUTH_RENEW_ACCESS_TOKEN_SUCCESS = 'Access Token has been renewed';
    public const string OAUTH_REVOKE_ACCESS_TOKEN_SUCCESS = 'Revoked Access Token';
}
