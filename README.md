# laravel-etrade
This project is currently under development and not ready for general use.

`laravel-etrade` is a Laravel package for the E*Trade v1 API which is a rewrite of [phpetrade](https://github.com/kevinrider/phpetrade) using standard Laravel features and removes the PHP OAuth PECL requirement in favor of Guzzle.

## Project Overview
This package aims to provide a robust and easy-to-use client for the ETrade API within Laravel applications.

## Current status
-   **EtradeApiClient**: Handles requests, manages tokens, and maps responses to DTOs.
    - ✅ Authorization (Oauth handling, GetAccessToken, RenewAccessToken, RevokeAccessToken)
    -  ❌ Accounts (GetAccountList, GetAccountBalance, GetAccountTransactions, GetAccountTransactionDetails, GetAccountPorfolio)
    -  ❌ Alerts (AlertsList, AlertsListDetails, AlertsDelete)

    -  ❌ Market (MarketGetQuotes, MarketLookUp, MarketGetOptionChain, MarketGetOptionExp)
    -  ❌ Order (ListOrders, PreviewOrder, PlaceOrder, ChangePreviewOrder, PlaceChangeOrder, CancelOrder)

## Key Technologies
-   **Language**: PHP 8.2+
-   **Framework**: Laravel 10+

