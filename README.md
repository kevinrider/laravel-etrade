<div align="center">
<img src="https://repository-images.githubusercontent.com/1093108803/57c68ce0-60a7-46c1-ac52-c8325ae473be" alt="social-preview" width="640">
</div>

<div align="center">
<a href="https://github.com/kevinrider/laravel-etrade/releases"><img src="https://img.shields.io/github/release/kevinrider/laravel-etrade.svg?style=flat-square" alt="Latest Version"></a>
<a href="https://github.com/kevinrider/laravel-etrade/actions"><img src="https://github.com/kevinrider/laravel-etrade/actions/workflows/tests.yml/badge.svg" alt="Unit tests"></a>
<a href="https://github.com/kevinrider/laravel-etrade/issues"><img src="https://img.shields.io/github/issues/kevinrider/laravel-etrade" alt="GitHub issues"></a>
<a href="https://github.com/kevinrider/laravel-etrade/blob/main/LICENSE"><img src="https://img.shields.io/github/license/kevinrider/laravel-etrade" alt="License"></a>
</div>

[Laravel](https://laravel.com/) client for the [E*TRADE v1 API](https://developer.etrade.com/). This is a rewrite of
[phpetrade](https://github.com/kevinrider/phpetrade) that uses Laravel
conventions, [Guzzle](https://github.com/guzzle/guzzle), [Carbon](https://github.com/briannesbitt/Carbon), and complete data transfer object (DTO) coverage for E\*Trade API request/response objects.

## Disclosure
**This package is not affiliated with, endorsed by, or supported by E\*TRADE or any of its affiliates or owners. Use at your own risk and review the E\*TRADE API terms before use.** 

**The developer is not responsible for any losses incurred from the use of this code. Laravel-etrade comes with absolutely no warranty and should not be used in actual trading unless you (the user) can read and understand the source code. Orders placed with this code (in production mode) will submit trades that will be advertised on the open market and will immediately be filled if a counterparty is present regardless of whether you made a mistake or not. It is your responsibility to understand, test (in the sandbox), and refine your trades and the code where necessary before using in a live market environment.**

**Use the sandbox environment before moving to production, especially if you are going to place orders! When starting in the production environment you should create and test your trades and code. This can include submitting orders when the regular market session is closed and setting the marketSession to REGULAR. You can also submit trades with a limit price that is far below the market bid if going long or well above the ask if you are going short. Do not use priceType=MARKET trades unless you want an immediate fill on the order. MARKET trades are definitely not recommended for options, especially those on low liquidity symbols or on strike prices that are deep OTM.**

## Features
- Access your E\*Trade accounts within a Laravel app!
- Guzzle-powered OAuth 1.0a flow and request signing
- DTOs for request/response payloads
- Order builder for previewing and placing orders
- Encrypted token storage and management via Laravel cache
- Interactive Artisan demo against your E\*Trade account

## Key files
- **EtradeApiClient**: [Full E\*Trade API coverage](https://apisb.etrade.com/docs/api/account/api-account-v1.html)
  - Authorization: request/renew/revoke access tokens
  - Accounts: list, balance, transactions, transaction details, portfolio
  - Alerts: list, details, delete
  - Market: quotes, lookup, option chains, option expirations
  - Orders: list, preview, place, change preview, place change, cancel
- **EtradeOrderBuilder**: Quickly and easily compose complex orders 
- **LarvelEtradeDemo**: Interactive cli command with extensive **EtradeApiClient** and **EtradeOrderBuilder** example usage

## Requirements
- PHP 8.3+
- Laravel 10+
- Guzzle 7.10+
- E\*TRADE API keys
- Configured Laravel cache driver

## Installation
Install via Composer:

```bash
composer require kevinrider/laravel-etrade
```

or without dev dependencies:
```bash
composer require kevinrider/laravel-etrade --no-dev
```

Publish the config file:

```bash
php artisan vendor:publish --provider="KevinRider\LaravelEtrade\LaravelEtradeServiceProvider"
```

You should consider locking to a specific laravel-etrade version in `composer.json`. For example in the `composer.json` require section:

```
{
  "require": {
          "kevinrider/laravel-etrade": "1.0.0"
  }
}
```
will prevent updates and lock to version 1.0.0. If newer versions are released you can test and then release to your Laravel app as needed.

## Configuration
The package is configured in `config/laravel-etrade.php`. Most likely the only variable you will change is `production` to `true` when you are ready to use your live E\*Trade account.

- `app_key` and `app_secret`: E\*TRADE application credentials
- `oauth_request_token_key`: cache key for the OAuth request token
- `oauth_access_token_key`: cache key for the OAuth access token
- `inactive_buffer_in_seconds`: auto-renew token buffer
- `production`: `true` for live, `false` for sandbox

The default `app_key` and `app_secret` config pulls from env. This means you can either set them in your `.env` file:

```env
ETRADE_APP_KEY=your_key
ETRADE_APP_SECRET=your_secret
```
**OR**

Copy the following into .bashrc (assuming you're using bash), updating as needed for your specific config:
```bash
export ETRADE_APP_KEY=your_key
export ETRADE_APP_SECRET=your_secret
```
Don't forget to apply the bash profile changes:
```bash
source ~/.bashrc # (or similar depending on your shell)
```

When approved by E\*Trade for API access, you will be issued two sets of keys one for production and one for the sandbox. You must copy your sandbox key/secret into `ETRADE_APP_KEY/TRADE_APP_SECRET` when using the sandbox and vice versa when switching to production.

## Authorization flow
The flow uses "out of band" (OOB) verification:

1. Call `getAuthorizationUrl()` to retrieve the login URL.
2. Log in to E\*TRADE, approve the app, and copy the verifier code.
3. Paste the verifier code into `requestAccessTokenAndStore()`.
4. The access token is stored in cache and used automatically.

## Usage
### EtradeApiClient
Resolve the client through the Laravel container:

```php
use KevinRider\LaravelEtrade\EtradeApiClient;
use KevinRider\LaravelEtrade\Dtos\Request\GetQuotesRequestDTO;

$client = app(EtradeApiClient::class);

$authUrl = $client->getAuthorizationUrl();
// Direct the user to $authUrl->authorizationUrl and collect the verifier
$client->requestAccessTokenAndStore($verifierCode);

$quotes = $client->getQuotes(new GetQuotesRequestDTO([
    'symbols' => ['AAPL', 'MSFT'],
]));
```

### EtradeOrderBuilder
Use the builder to generate preview/place order request DTOs:

```php
use KevinRider\LaravelEtrade\EtradeOrderBuilder;

$builder = EtradeOrderBuilder::forAccount($accountIdKey)
    ->orderType('EQ')
    ->withSymbol('AAPL')
    ->gfd()
    ->limit(185.00)
    ->addEquity('BUY', 1);

$previewRequest = $builder->buildPreviewRequest();
```

### Exceptions
- `EtradeAuthException`: thrown when tokens are missing or expired in cache.
- `EtradeApiException`: thrown on non-2XX responses, includes status and headers.
- `Illuminate\Validation\ValidationException`: thrown on incomplete/invalid request params.
- `InvalidArgumentException`: thrown on incomplete/invalid orders.

## Examples and demo

`LarvelEtradeDemo` serves as a demonstration of all E\*Trade API endpoints but also as `EtradeApiClient` and `EtradeOrderBuilder` example code in a variety of scenarios and trade types.

Run the interactive demo via artisan:

```bash
php artisan laravel-etrade:demo
```

It walks through OAuth, read-only endpoints, and order flows with warnings before any destructive actions or placing any orders. 

**Be careful!** `LarvelEtradeDemo` will place live orders if production mode is used!

## Testing

If you installed with dev dependency the following will run all tests:
```bash
composer test
```

## Further reading
- [phpetrade](https://github.com/kevinrider/phpetrade): Additional notes and FAQ that may be useful.
- [E\*Trade Developer](https://developer.etrade.com/): E\*Trade API documentation
