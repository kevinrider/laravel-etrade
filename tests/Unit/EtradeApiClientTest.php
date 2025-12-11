<?php

use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use KevinRider\LaravelEtrade\Dtos\AccountBalance\CashDTO;
use KevinRider\LaravelEtrade\Dtos\AccountBalance\ComputedBalanceDTO;
use KevinRider\LaravelEtrade\Dtos\AccountBalance\MarginDTO;
use KevinRider\LaravelEtrade\Dtos\AccountBalanceResponseDTO;
use KevinRider\LaravelEtrade\Dtos\Alerts\AlertDTO;
use KevinRider\LaravelEtrade\Dtos\Alerts\FailedAlertsDTO;
use KevinRider\LaravelEtrade\Dtos\AuthorizationUrlDTO;
use KevinRider\LaravelEtrade\Dtos\CancelOrderResponseDTO;
use KevinRider\LaravelEtrade\Dtos\DeleteAlertsResponseDTO;
use KevinRider\LaravelEtrade\Dtos\EtradeAccessTokenDTO;
use KevinRider\LaravelEtrade\Dtos\GetQuotesResponseDTO;
use KevinRider\LaravelEtrade\Dtos\ListAlertDetailsResponseDTO;
use KevinRider\LaravelEtrade\Dtos\ListAlertsResponseDTO;
use KevinRider\LaravelEtrade\Dtos\ListTransactionDetailsResponseDTO;
use KevinRider\LaravelEtrade\Dtos\ListTransactionsResponseDTO;
use KevinRider\LaravelEtrade\Dtos\LookupResponseDTO;
use KevinRider\LaravelEtrade\Dtos\OptionChainResponseDTO;
use KevinRider\LaravelEtrade\Dtos\OptionExpireDateResponseDTO;
use KevinRider\LaravelEtrade\Dtos\LookupProduct\DataDTO;
use KevinRider\LaravelEtrade\Dtos\Options\ExpirationDateDTO;
use KevinRider\LaravelEtrade\Dtos\Options\OptionChainPairDTO;
use KevinRider\LaravelEtrade\Dtos\Options\OptionDetailsDTO;
use KevinRider\LaravelEtrade\Dtos\Options\OptionGreeksDTO;
use KevinRider\LaravelEtrade\Dtos\Options\SelectedEDDTO;
use KevinRider\LaravelEtrade\Dtos\OrdersResponseDTO;
use KevinRider\LaravelEtrade\Dtos\PlaceOrderResponseDTO;
use KevinRider\LaravelEtrade\Dtos\PreviewOrderResponseDTO;
use KevinRider\LaravelEtrade\Dtos\ListOrders\InstrumentDTO;
use KevinRider\LaravelEtrade\Dtos\ListOrders\LotDTO;
use KevinRider\LaravelEtrade\Dtos\ListOrders\OrderDTO;
use KevinRider\LaravelEtrade\Dtos\ListOrders\OrderDetailDTO;
use KevinRider\LaravelEtrade\Dtos\ListOrders\ProductDTO as ListOrdersProductDTO;
use KevinRider\LaravelEtrade\Dtos\Request\AccountBalanceRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\DeleteAlertsRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\GetOptionChainsRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\GetOptionExpireDatesRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\GetQuotesRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\ListAlertDetailsRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\ListAlertsRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\CancelOrderRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\ListOrdersRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\ListTransactionDetailsRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\ListTransactionsRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\LookupRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\PlaceOrderRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\PreviewOrderRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\ViewPortfolioRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Transaction\BrokerageDTO;
use KevinRider\LaravelEtrade\Dtos\Transaction\CategoryDTO;
use KevinRider\LaravelEtrade\Dtos\ViewPortfolio\ProductDTO;
use KevinRider\LaravelEtrade\Dtos\ViewPortfolio\QuickViewDTO;
use KevinRider\LaravelEtrade\Dtos\ViewPortfolioResponseDTO;
use KevinRider\LaravelEtrade\EtradeApiClient;
use KevinRider\LaravelEtrade\EtradeConfig;
use KevinRider\LaravelEtrade\Exceptions\EtradeApiException;

beforeEach(function () {
    \Config::set('laravel-etrade.oauth_request_token_key', 'etrade.oauth.request_token');
    \Config::set('laravel-etrade.oauth_access_token_key', 'etrade.oauth.access_token');
    \Config::set('laravel-etrade.inactive_buffer_in_seconds', 300); // 5 minutes
    Cache::clear();
});

it('can get authorization url successfully', function () {
    // Mock the Guzzle client to return a successful response
    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('get')
        ->once()
        ->andReturn(new Response(200, [], 'oauth_token=test_oauth_token&oauth_token_secret=test_oauth_token_secret'));

    $appKey = 'test_app_key';
    $appSecret = 'test_app_secret';
    $etradeClient = new EtradeApiClient($appKey, $appSecret);
    $authorizationUrlDto = $etradeClient->getAuthorizationUrl();

    expect($authorizationUrlDto)->toBeInstanceOf(AuthorizationUrlDTO::class)
        ->and($authorizationUrlDto->authorizationUrl)->toBe(EtradeConfig::AUTHORIZE_URL . '?key=' . $appKey . '&token=test_oauth_token')
        ->and($authorizationUrlDto->oauthToken)->toBe('test_oauth_token');

    // Verify that the request token is cached
    $cachedToken = Cache::get(config('laravel-etrade.oauth_request_token_key'));
    expect($cachedToken)->not->toBeNull();
    $decryptedToken = json_decode(Crypt::decryptString($cachedToken), true);
    expect($decryptedToken['oauth_token'])->toBe('test_oauth_token')
        ->and($decryptedToken['oauth_token_secret'])->toBe('test_oauth_token_secret');
});

it('throws exception on non-200 response for authorization url', function () {
    // Mock the Guzzle client to return a non-200 response
    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('get')
        ->once()
        ->andReturn(new Response(500, [], 'Internal Server Error'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');

    expect(function () use ($etradeClient) {
        $etradeClient->getAuthorizationUrl();
    })->toThrow(EtradeApiException::class, 'Failed to get request token');
});

it('throws exception on malformed response for authorization url', function () {
    // Mock the Guzzle client to return a 200 response but with malformed body
    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('get')
        ->once()
        ->andReturn(new Response(200, [], 'some_other_param=value'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');

    expect(function () use ($etradeClient) {
        $etradeClient->getAuthorizationUrl();
    })->toThrow(EtradeApiException::class, 'Malformed get request token response');
});

it('can request and store access token successfully', function () {
    // Cache a dummy encrypted request token
    $requestToken = [
        'oauth_token' => 'cached_request_token',
        'oauth_token_secret' => 'cached_request_token_secret',
    ];
    Cache::put(
        config('laravel-etrade.oauth_request_token_key'),
        Crypt::encryptString(json_encode($requestToken)),
        now()->addMinutes(5)
    );

    // Mock a successful Guzzle response for the access token request
    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('get')
        ->once()
        ->andReturn(new Response(200, [], 'oauth_token=new_access_token&oauth_token_secret=new_access_token_secret'));

    $appKey = 'test_app_key';
    $appSecret = 'test_app_secret';
    $verifierCode = 'test_verifier_code';
    $etradeClient = new EtradeApiClient($appKey, $appSecret);
    $etradeClient->requestAccessTokenAndStore($verifierCode);

    // Verify that the access token is cached
    $cachedAccessToken = Cache::get(config('laravel-etrade.oauth_access_token_key'));
    expect($cachedAccessToken)->not->toBeNull();
    $decryptedAccessToken = json_decode(Crypt::decryptString($cachedAccessToken), true);
    expect($decryptedAccessToken['oauth_token'])->toBe('new_access_token')
        ->and($decryptedAccessToken['oauth_token_secret'])->toBe('new_access_token_secret')
        ->and($decryptedAccessToken['inactive_at'])->toBeInt();
});

it('throws exception if request tokens are missing from cache', function () {
    // Ensure the cache is empty for the request token
    Cache::forget(config('laravel-etrade.oauth_request_token_key'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');

    expect(function () use ($etradeClient) {
        $etradeClient->requestAccessTokenAndStore('some_verifier');
    })->toThrow(EtradeApiException::class, 'Request tokens missing or expired.');
});

it('throws exception on non-200 response for access token request', function () {
    // Cache a dummy encrypted request token
    $requestToken = [
        'oauth_token' => 'cached_request_token',
        'oauth_token_secret' => 'cached_request_token_secret',
    ];
    Cache::put(
        config('laravel-etrade.oauth_request_token_key'),
        Crypt::encryptString(json_encode($requestToken)),
        now()->addMinutes(5)
    );

    // Mock a non-200 Guzzle response for the access token request
    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('get')
        ->once()
        ->andReturn(new Response(500, [], 'Internal Server Error'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');

    expect(function () use ($etradeClient) {
        $etradeClient->requestAccessTokenAndStore('test_verifier_code');
    })->toThrow(EtradeApiException::class, 'Failed to get access token');
});

it('throws exception on malformed response for access token request', function () {
    // Cache a dummy encrypted request token
    $requestToken = [
        'oauth_token' => 'cached_request_token',
        'oauth_token_secret' => 'cached_request_token_secret',
    ];
    Cache::put(
        config('laravel-etrade.oauth_request_token_key'),
        Crypt::encryptString(json_encode($requestToken)),
        now()->addMinutes(5)
    );

    // Mock a 200 Guzzle response but with malformed body
    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('get')
        ->once()
        ->andReturn(new Response(200, [], 'some_other_param=value'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');

    expect(function () use ($etradeClient) {
        $etradeClient->requestAccessTokenAndStore('test_verifier_code');
    })->toThrow(EtradeApiException::class, 'Malformed get access token response');
});

it('can get access token successfully from cache', function () {
    // Cache a valid, non-expired access token
    $accessToken = [
        'oauth_token' => 'cached_access_token',
        'oauth_token_secret' => 'cached_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(), // Not expiring soon
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    $etradeClient = \Mockery::mock(EtradeApiClient::class, ['test_key', 'test_secret'])->makePartial();
    $etradeClient->shouldNotReceive('renewAccessToken');

    $returnedTokenDto = $etradeClient->getAccessToken();

    expect($returnedTokenDto)->toBeInstanceOf(EtradeAccessTokenDTO::class)
        ->and($returnedTokenDto->oauthToken)->toBe('cached_access_token')
        ->and($returnedTokenDto->oauthTokenSecret)->toBe('cached_access_token_secret');
});

it('renews access token if it is about to expire', function () {
    // Cache an access token that is about to expire
    $accessToken = [
        'oauth_token' => 'expiring_access_token',
        'oauth_token_secret' => 'expiring_access_token_secret',
        'inactive_at' => now()->addSeconds(config('laravel-etrade.inactive_buffer_in_seconds') - 1)->getTimestamp(), // Expiring soon
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    $renewedAccessTokenDto = new EtradeAccessTokenDTO([
        'oauthToken' => 'renewed_access_token',
        'oauthTokenSecret' => 'renewed_access_token_secret',
        'inactiveAt' => now()->addHour(2),
    ]);

    $etradeClient = \Mockery::mock(EtradeApiClient::class, ['test_key', 'test_secret'])->makePartial();
    $etradeClient->shouldAllowMockingProtectedMethods();
    $etradeClient->shouldReceive('renewAccessToken')
        ->once()
        ->andReturn($renewedAccessTokenDto);

    $returnedTokenDto = $etradeClient->getAccessToken();

    expect($returnedTokenDto)->toBeInstanceOf(EtradeAccessTokenDTO::class)
        ->and($returnedTokenDto->oauthToken)->toBe('renewed_access_token');
});

it('throws exception if cached access tokens are missing', function () {
    // Ensure the cache is empty for the access token
    Cache::forget(config('laravel-etrade.oauth_access_token_key'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');

    expect(function () use ($etradeClient) {
        $etradeClient->getAccessToken();
    })->toThrow(EtradeApiException::class, 'Cached access tokens missing or expired.');
});

it('can renew access token successfully', function () {
    // Cache an access token
    $accessToken = [
        'oauth_token' => 'old_access_token',
        'oauth_token_secret' => 'old_access_token_secret',
        'inactive_at' => now()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    // Mock a successful Guzzle response for the renew token request
    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('get')
        ->once()
        ->andReturn(new Response(200, [], EtradeConfig::OAUTH_RENEW_ACCESS_TOKEN_SUCCESS));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $renewedTokenDto = $etradeClient->renewAccessToken();

    expect($renewedTokenDto)->toBeInstanceOf(EtradeAccessTokenDTO::class)
        ->and($renewedTokenDto->oauthToken)->toBe('old_access_token')
        ->and($renewedTokenDto->oauthTokenSecret)->toBe('old_access_token_secret');

    // Verify that the access token is updated in the cache with a new inactive_at time
    $cachedAccessToken = Cache::get(config('laravel-etrade.oauth_access_token_key'));
    expect($cachedAccessToken)->not->toBeNull();
    $decryptedAccessToken = json_decode(Crypt::decryptString($cachedAccessToken), true);
    expect($decryptedAccessToken['oauth_token'])->toBe('old_access_token')
        ->and($decryptedAccessToken['oauth_token_secret'])->toBe('old_access_token_secret')
        ->and($decryptedAccessToken['inactive_at'])->toBeGreaterThan($accessToken['inactive_at']);
});

it('throws exception on non-200 response for renew access token request', function () {
    // Cache an access token
    $accessToken = [
        'oauth_token' => 'old_access_token',
        'oauth_token_secret' => 'old_access_token_secret',
        'inactive_at' => now()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    // Mock a non-200 Guzzle response for the renew token request
    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('get')
        ->once()
        ->andReturn(new Response(500, [], 'Internal Server Error'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');

    expect(function () use ($etradeClient) {
        $etradeClient->renewAccessToken();
    })->toThrow(EtradeApiException::class, 'Failed to renew access token');
});

it('throws exception on malformed response for renew access token request', function () {
    // Cache an access token
    $accessToken = [
        'oauth_token' => 'old_access_token',
        'oauth_token_secret' => 'old_access_token_secret',
        'inactive_at' => now()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    // Mock a 200 Guzzle response but with malformed body
    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('get')
        ->once()
        ->andReturn(new Response(400, [], 'failure'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');

    expect(function () use ($etradeClient) {
        $etradeClient->renewAccessToken();
    })->toThrow(EtradeApiException::class, 'Failed to renew access token');
});

it('throws exception if renewing access token when no token is cached', function () {
    // Ensure the cache is empty for the access token
    Cache::forget(config('laravel-etrade.oauth_access_token_key'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');

    expect(function () use ($etradeClient) {
        $etradeClient->renewAccessToken();
    })->toThrow(EtradeApiException::class, 'Cached access tokens missing or expired.');
});

it('can revoke access token successfully', function () {
    // Cache an access token
    $accessToken = [
        'oauth_token' => 'access_token_to_revoke',
        'oauth_token_secret' => 'access_token_secret_to_revoke',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    // Mock a successful Guzzle response for the revoke token request
    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('get')
        ->once()
        ->andReturn(new Response(200, [], EtradeConfig::OAUTH_REVOKE_ACCESS_TOKEN_SUCCESS));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $etradeClient->revokeAccessToken();

    // No exception should be thrown, so the test passes if it reaches here.
    expect(true)->toBeTrue();
});

it('throws exception on non-200 response for revoke access token request', function () {
    // Cache an access token
    $accessToken = [
        'oauth_token' => 'access_token_to_revoke',
        'oauth_token_secret' => 'access_token_secret_to_revoke',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    // Mock a non-200 Guzzle response for the revoke token request
    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('get')
        ->once()
        ->andReturn(new Response(500, [], 'Internal Server Error'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');

    expect(function () use ($etradeClient) {
        $etradeClient->revokeAccessToken();
    })->toThrow(EtradeApiException::class, 'Failed to revoke access token');
});

it('throws exception on malformed response for revoke access token request', function () {
    // Cache an access token
    $accessToken = [
        'oauth_token' => 'access_token_to_revoke',
        'oauth_token_secret' => 'access_token_secret_to_revoke',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    // Mock a 200 Guzzle response but with malformed body
    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('get')
        ->once()
        ->andReturn(new Response(500, [], 'failure'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');

    expect(function () use ($etradeClient) {
        $etradeClient->revokeAccessToken();
    })->toThrow(EtradeApiException::class, 'Failed to revoke access token');
});

it('throws exception if revoking access token when no token is cached', function () {
    // Ensure the cache is empty for the access token
    Cache::forget(config('laravel-etrade.oauth_access_token_key'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');

    expect(function () use ($etradeClient) {
        $etradeClient->revokeAccessToken();
    })->toThrow(EtradeApiException::class, 'Cached access tokens missing or expired.');
});

it('can get account list successfully', function () {
    // Cache an access token
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    // Mock a successful Guzzle response with the XML fixture
    $xmlResponse = file_get_contents(__DIR__ . '/../fixtures/AccountListResponse.xml');
    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('get')
        ->once()
        ->andReturn(new Response(200, [], $xmlResponse));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $accountListDto = $etradeClient->getAccountList();

    expect($accountListDto)->toBeInstanceOf(\KevinRider\LaravelEtrade\Dtos\AccountListResponseDTO::class)
        ->and($accountListDto->accounts)->toBeArray()
        ->and(count($accountListDto->accounts))->toBe(2);

    $account1 = $accountListDto->accounts[0];
    expect($account1->accountId)->toBe('840104290')
        ->and($account1->accountIdKey)->toBe('JIdOIAcSpwR1Jva7RQBraQ')
        ->and($account1->accountMode)->toBe('MARGIN')
        ->and($account1->accountDesc)->toBe('INDIVIDUAL')
        ->and($account1->accountName)->toBe('Individual Brokerage')
        ->and($account1->accountType)->toBe('INDIVIDUAL')
        ->and($account1->institutionType)->toBe('BROKERAGE')
        ->and($account1->accountStatus)->toBe('ACTIVE')
        ->and($account1->closedDate)->toBe(0);

    $account2 = $accountListDto->accounts[1];
    expect($account2->accountId)->toBe('840104291')
        ->and($account2->accountIdKey)->toBe('JIdOIAcSpwR1Jva7RQBraq')
        ->and($account2->accountMode)->toBe('MARGIN')
        ->and($account2->accountDesc)->toBe('INDIVIDUAL')
        ->and($account2->accountName)->toBe('')
        ->and($account2->accountType)->toBe('INDIVIDUAL')
        ->and($account2->institutionType)->toBe('BROKERAGE')
        ->and($account2->accountStatus)->toBe('ACTIVE')
        ->and($account2->closedDate)->toBe(0);
});

it('throws exception on non-200 response for get account list', function () {
    // Cache an access token
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    // Mock a non-200 Guzzle response
    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('get')
        ->once()
        ->andReturn(new Response(500, [], 'Internal Server Error'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');

    expect(function () use ($etradeClient) {
        $etradeClient->getAccountList();
    })->toThrow(EtradeApiException::class, 'Failed to get account list');
});

it('throws exception if getting account list when no token is cached', function () {
    // Ensure the cache is empty for the access token
    Cache::forget(config('laravel-etrade.oauth_access_token_key'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');

    expect(function () use ($etradeClient) {
        $etradeClient->getAccountList();
    })->toThrow(EtradeApiException::class, 'Cached access tokens missing or expired.');
});

it('can get account balance successfully', function () {
    // Cache an access token
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    // Mock a successful Guzzle response with the XML fixture
    $xmlResponse = file_get_contents(__DIR__ . '/../fixtures/GetAccountBalanceResponse.xml');
    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('get')
        ->once()
        ->andReturn(new Response(200, [], $xmlResponse));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $accountBalanceRequestDto = new AccountBalanceRequestDTO([
        'accountIdKey' => 'test_account_id_key',
    ]);
    $accountBalanceDto = $etradeClient->getAccountBalance($accountBalanceRequestDto);

    expect($accountBalanceDto)->toBeInstanceOf(AccountBalanceResponseDTO::class)
        ->and($accountBalanceDto->accountId)->toBe('835649790')
        ->and($accountBalanceDto->accountType)->toBe('PDT_ACCOUNT')
        ->and($accountBalanceDto->optionLevel)->toBe('LEVEL_4')
        ->and($accountBalanceDto->accountDescription)->toBe('KRITHH TT')
        ->and($accountBalanceDto->quoteMode)->toBe('6')
        ->and($accountBalanceDto->dayTraderStatus)->toBe('PDT_MIN_EQUITY_RES_1XK')
        ->and($accountBalanceDto->accountMode)->toBe('PDT ACCOUNT')
        ->and($accountBalanceDto->cash)->toBeInstanceOf(CashDTO::class)
        ->and($accountBalanceDto->cash->fundsForOpenOrdersCash)->toBe(0.0)
        ->and($accountBalanceDto->cash->moneyMktBalance)->toBe(0.0)
        ->and($accountBalanceDto->computedBalance)->toBeInstanceOf(ComputedBalanceDTO::class)
        ->and($accountBalanceDto->computedBalance->cashAvailableForInvestment)->toBe(0.0)
        ->and($accountBalanceDto->computedBalance->netCash)->toBe(93921.44)
        ->and($accountBalanceDto->computedBalance->cashBalance)->toBe(93921.44)
        ->and($accountBalanceDto->computedBalance->settledCashForInvestment)->toBe(0.0)
        ->and($accountBalanceDto->computedBalance->unSettledCashForInvestment)->toBe(0.0)
        ->and($accountBalanceDto->computedBalance->fundsWithheldFromPurchasePower)->toBe(0.0)
        ->and($accountBalanceDto->computedBalance->marginBuyingPower)->toBe(0.0)
        ->and($accountBalanceDto->computedBalance->cashBuyingPower)->toBe(93921.44)
        ->and($accountBalanceDto->computedBalance->dtMarginBuyingPower)->toBe(0.0)
        ->and($accountBalanceDto->computedBalance->dtCashBuyingPower)->toBe(0.0)
        ->and($accountBalanceDto->computedBalance->shortAdjustBalance)->toBe(0.0)
        ->and($accountBalanceDto->computedBalance->regtEquity)->toBe(0.0)
        ->and($accountBalanceDto->computedBalance->regtEquityPercent)->toBe(0.0)
        ->and($accountBalanceDto->margin)->toBeInstanceOf(MarginDTO::class)
        ->and($accountBalanceDto->margin->dtCashOpenOrderReserve)->toBe(0.0)
        ->and($accountBalanceDto->margin->dtMarginOpenOrderReserve)->toBe(0.0)
        ->and(property_exists($accountBalanceDto, 'lending') && isset($accountBalanceDto->lending))->toBeFalse();
});

it('throws exception on non-200 response for get account balance', function () {
    // Cache an access token
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    // Mock a non-200 Guzzle response
    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('get')
        ->once()
        ->andReturn(new Response(500, [], 'Internal Server Error'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $accountBalanceRequestDto = new AccountBalanceRequestDTO([
        'accountIdKey' => 'test_account_id_key',
    ]);

    expect(function () use ($etradeClient, $accountBalanceRequestDto) {
        $etradeClient->getAccountBalance($accountBalanceRequestDto);
    })->toThrow(EtradeApiException::class, 'Failed to get account balance');
});

it('throws exception if getting account balance when no token is cached', function () {
    // Ensure the cache is empty for the access token
    Cache::forget(config('laravel-etrade.oauth_access_token_key'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $accountBalanceRequestDto = new AccountBalanceRequestDTO([
        'accountIdKey' => 'test_account_id_key',
    ]);

    expect(function () use ($etradeClient, $accountBalanceRequestDto) {
        $etradeClient->getAccountBalance($accountBalanceRequestDto);
    })->toThrow(EtradeApiException::class, 'Cached access tokens missing or expired.');
});

it('can get account transactions successfully', function () {
    // Cache an access token
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    // Mock a successful Guzzle response with the XML fixture
    $xmlResponse = file_get_contents(__DIR__ . '/../fixtures/ListTransactionsResponse.xml');
    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('get')
        ->once()
        ->andReturn(new Response(200, [], $xmlResponse));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $listTransactionsRequestDto = new ListTransactionsRequestDTO([
        'accountIdKey' => 'test_account_id_key',
    ]);
    $listTransactionsDto = $etradeClient->getAccountTransactions($listTransactionsRequestDto);

    expect($listTransactionsDto)->toBeInstanceOf(ListTransactionsResponseDTO::class)
        ->and($listTransactionsDto->transactions)->toBeArray()
        ->and(count($listTransactionsDto->transactions))->toBe(3)
        ->and($listTransactionsDto->pageMarkers)->toBe('eNptjkEOgjAQRfecogcgoYNSJGm6YUWixqgXqDAxRqSx0B2Ht1AwWuiiSf%2F8vjeEn%2BQdc2WarmhyqZVpsRYxj9bigPCr6mR9xlLpapyKhEeLbO59GQPQS2xneBzN64b6gm%2BDTYmChjSEMLY3dSt484C4b0W1f7QDhEzBQeonagE7YAlQeyBlrO97R5mmwdx2VAG%2FjpEdefCFzLdtM2sDlmabZM32p4t9nef7AOzdfmc%3D')
        ->and($listTransactionsDto->moreTransactions)->toBe('false')
        ->and($listTransactionsDto->transactionCount)->toBe('3')
        ->and($listTransactionsDto->totalCount)->toBe('5');

    $transaction1 = $listTransactionsDto->transactions[0];
    expect($transaction1->transactionId)->toBe('18165100001766')
        ->and($transaction1->accountId)->toBe('835649790')
        ->and($transaction1->transactionDate)->toBe(1528948800000)
        ->and($transaction1->postDate)->toBe(1528948800000)
        ->and($transaction1->amount)->toBe(-2.0)
        ->and($transaction1->description)->toBe('ACH WITHDRAWL REFID:109187276;')
        ->and($transaction1->transactionType)->toBe('Transfer')
        ->and($transaction1->brokerage)->toBeInstanceOf(BrokerageDTO::class)
        ->and($transaction1->brokerage->quantity)->toBe(0.0)
        ->and($transaction1->brokerage->price)->toBe(0.0)
        ->and($transaction1->brokerage->settlementCurrency)->toBe('USD')
        ->and($transaction1->brokerage->paymentCurrency)->toBe('USD')
        ->and($transaction1->brokerage->fee)->toBe(0.0);

    $transaction2 = $listTransactionsDto->transactions[1];
    expect($transaction2->transactionId)->toBe('18158100000983')
        ->and($transaction2->accountId)->toBe('835649790')
        ->and($transaction2->transactionDate)->toBe(1528344000000)
        ->and($transaction2->postDate)->toBe(1528344000000)
        ->and($transaction2->amount)->toBe(-2.0)
        ->and($transaction2->description)->toBe('ACH WITHDRAWL REFID:98655276;')
        ->and($transaction2->transactionType)->toBe('Transfer')
        ->and($transaction2->brokerage)->toBeInstanceOf(BrokerageDTO::class)
        ->and($transaction2->brokerage->quantity)->toBe(0.0)
        ->and($transaction2->brokerage->price)->toBe(0.0)
        ->and($transaction2->brokerage->settlementCurrency)->toBe('USD')
        ->and($transaction2->brokerage->paymentCurrency)->toBe('USD')
        ->and($transaction2->brokerage->fee)->toBe(0.0);

    $transaction3 = $listTransactionsDto->transactions[2];
    expect($transaction3->transactionId)->toBe('18151100002634')
        ->and($transaction3->accountId)->toBe('835649790')
        ->and($transaction3->transactionDate)->toBe(1527739200000)
        ->and($transaction3->postDate)->toBe(1527739200000)
        ->and($transaction3->amount)->toBe(-2.0)
        ->and($transaction3->description)->toBe('ACH WITHDRAWL REFID:87756276;')
        ->and($transaction3->transactionType)->toBe('Transfer')
        ->and($transaction3->brokerage)->toBeInstanceOf(BrokerageDTO::class)
        ->and($transaction3->brokerage->quantity)->toBe(0.0)
        ->and($transaction3->brokerage->price)->toBe(0.0)
        ->and($transaction3->brokerage->settlementCurrency)->toBe('USD')
        ->and($transaction3->brokerage->paymentCurrency)->toBe('USD')
        ->and($transaction3->brokerage->fee)->toBe(0.0);
});

it('throws exception on non-200 response for get account transactions', function () {
    // Cache an access token
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    // Mock a non-200 Guzzle response
    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('get')
        ->once()
        ->andReturn(new Response(500, [], 'Internal Server Error'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $listTransactionsRequestDto = new ListTransactionsRequestDTO([
        'accountIdKey' => 'test_account_id_key',
    ]);

    expect(function () use ($etradeClient, $listTransactionsRequestDto) {
        $etradeClient->getAccountTransactions($listTransactionsRequestDto);
    })->toThrow(EtradeApiException::class, 'Failed to get account transactions');
});

it('throws exception if getting account transactions when no token is cached', function () {
    // Ensure the cache is empty for the access token
    Cache::forget(config('laravel-etrade.oauth_access_token_key'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $listTransactionsRequestDto = new ListTransactionsRequestDTO([
        'accountIdKey' => 'test_account_id_key',
    ]);

    expect(function () use ($etradeClient, $listTransactionsRequestDto) {
        $etradeClient->getAccountTransactions($listTransactionsRequestDto);
    })->toThrow(EtradeApiException::class, 'Cached access tokens missing or expired.');
});

it('can get account transaction details successfully', function () {
    // Cache an access token
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    // Mock a successful Guzzle response with the XML fixture
    $xmlResponse = file_get_contents(__DIR__ . '/../fixtures/ListTransactionDetailsResponse.xml');
    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('get')
        ->once()
        ->andReturn(new Response(200, [], $xmlResponse));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $listTransactionDetailsRequestDto = new ListTransactionDetailsRequestDTO([
        'accountIdKey' => 'test_account_id_key',
        'transactionId' => 'test_transaction_id',
    ]);
    $listTransactionDetailsDto = $etradeClient->getAccountTransactionDetails($listTransactionDetailsRequestDto);

    expect($listTransactionDetailsDto)->toBeInstanceOf(ListTransactionDetailsResponseDTO::class);

    $transaction = $listTransactionDetailsDto->transaction;
    expect($transaction->transactionId)->toBe('18144100000861')
        ->and($transaction->accountId)->toBe('835649790')
        ->and($transaction->transactionDate)->toBe(1527134400000)
        ->and($transaction->amount)->toBe(-2.0)
        ->and($transaction->description)->toBe('ACH WITHDRAWL REFID:77521276;')
        ->and($transaction->category)->toBeInstanceOf(CategoryDTO::class)
        ->and($transaction->category->categoryId)->toBe('0')
        ->and($transaction->category->parentId)->toBe('0')
        ->and($transaction->category->categoryName)->toBe('')
        ->and($transaction->category->parentName)->toBe('')
        ->and($transaction->brokerage)->toBeInstanceOf(BrokerageDTO::class)
        ->and($transaction->brokerage->transactionType)->toBe('Transfer')
        ->and($transaction->brokerage->quantity)->toBe(0.0)
        ->and($transaction->brokerage->price)->toBe(0.0)
        ->and($transaction->brokerage->settlementCurrency)->toBe('USD')
        ->and($transaction->brokerage->paymentCurrency)->toBe('USD')
        ->and($transaction->brokerage->fee)->toBe(0.0)
        ->and($transaction->brokerage->memo)->toBe('')
        ->and($transaction->brokerage->orderNo)->toBe('0');
});

it('throws exception on non-200 response for get account transaction details', function () {
    // Cache an access token
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    // Mock a non-200 Guzzle response
    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('get')
        ->once()
        ->andReturn(new Response(500, [], 'Internal Server Error'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $listTransactionDetailsRequestDto = new ListTransactionDetailsRequestDTO([
        'accountIdKey' => 'test_account_id_key',
        'transactionId' => 'test_transaction_id',
    ]);

    expect(function () use ($etradeClient, $listTransactionDetailsRequestDto) {
        $etradeClient->getAccountTransactionDetails($listTransactionDetailsRequestDto);
    })->toThrow(EtradeApiException::class, 'Failed to get account transaction details');
});

it('throws exception if getting account transaction details when no token is cached', function () {
    // Ensure the cache is empty for the access token
    Cache::forget(config('laravel-etrade.oauth_access_token_key'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $listTransactionDetailsRequestDto = new ListTransactionDetailsRequestDTO([
        'accountIdKey' => 'test_account_id_key',
        'transactionId' => 'test_transaction_id',
    ]);

    expect(function () use ($etradeClient, $listTransactionDetailsRequestDto) {
        $etradeClient->getAccountTransactionDetails($listTransactionDetailsRequestDto);
    })->toThrow(EtradeApiException::class, 'Cached access tokens missing or expired.');
});

it('can view portfolio successfully', function () {
    // Cache an access token
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    // Mock a successful response using the fixture
    $xmlResponse = file_get_contents(__DIR__ . '/../fixtures/ViewPortfolioResponse.xml');
    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('get')
        ->once()
        ->andReturn(new Response(200, [], $xmlResponse));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $viewPortfolioRequestDto = new ViewPortfolioRequestDTO([
        'accountIdKey' => 'test_account_id_key',
    ]);
    $portfolioDto = $etradeClient->getViewPortfolio($viewPortfolioRequestDto);

    expect($portfolioDto)->toBeInstanceOf(ViewPortfolioResponseDTO::class)
        ->and($portfolioDto->accountId)->toEqual(835547880)
        ->and($portfolioDto->totalPages)->toEqual(1)
        ->and($portfolioDto->positions)->toBeArray()
        ->and(count($portfolioDto->positions))->toBe(2);

    $firstPosition = $portfolioDto->positions[0];
    expect($firstPosition->positionId)->toEqual(10087531)
        ->and($firstPosition->symbolDescription)->toBe('A')
        ->and($firstPosition->dateAcquired)->toEqual(-68400000)
        ->and($firstPosition->pricePaid)->toEqual(0.0)
        ->and($firstPosition->commissions)->toEqual(0.0)
        ->and($firstPosition->otherFees)->toEqual(0.0)
        ->and($firstPosition->quantity)->toEqual(-120.0)
        ->and($firstPosition->positionIndicator)->toBe('TYPE2')
        ->and($firstPosition->positionType)->toBe('SHORT')
        ->and($firstPosition->daysGain)->toEqual(190.80)
        ->and($firstPosition->daysGainPct)->toEqual(2.4472)
        ->and($firstPosition->marketValue)->toEqual(-7605.60)
        ->and($firstPosition->totalCost)->toEqual(0.0)
        ->and($firstPosition->totalGain)->toEqual(-7605.60)
        ->and($firstPosition->pctOfPortfolio)->toEqual(-0.0008)
        ->and($firstPosition->costPerShare)->toEqual(0.0)
        ->and($firstPosition->todayCommissions)->toEqual(0.0)
        ->and($firstPosition->todayFees)->toEqual(0.0)
        ->and($firstPosition->todayPricePaid)->toEqual(0.0)
        ->and($firstPosition->todayQuantity)->toEqual(0.0)
        ->and($firstPosition->adjPrevClose)->toEqual(64.97)
        ->and($firstPosition->lotsDetails)->toBe('https://api.etrade.com/v1/accounts/JDIozUumZpHdgbIjMnAAHQ/portfolio/10087531')
        ->and($firstPosition->quoteDetails)->toBe('https://api.etrade.com/v1/market/quote/A')
        ->and($firstPosition->product)->toBeInstanceOf(ProductDTO::class)
        ->and($firstPosition->product->symbol)->toBe('A')
        ->and($firstPosition->product->securityType)->toBe('EQ')
        ->and($firstPosition->product->expiryDay)->toEqual(0)
        ->and($firstPosition->product->expiryMonth)->toEqual(0)
        ->and($firstPosition->product->expiryYear)->toEqual(0)
        ->and($firstPosition->product->strikePrice)->toEqual(0.0)
        ->and($firstPosition->quick)->toBeInstanceOf(QuickViewDTO::class)
        ->and($firstPosition->quick->change)->toEqual(-1.59)
        ->and($firstPosition->quick->changePct)->toEqual(-2.4472)
        ->and($firstPosition->quick->lastTrade)->toEqual(63.38)
        ->and($firstPosition->quick->lastTradeTime)->toEqual(1529429280)
        ->and($firstPosition->quick->quoteStatus)->toBe('DELAYED')
        ->and($firstPosition->quick->volume)->toEqual(2431617);

    $secondPosition = $portfolioDto->positions[1];
    expect($secondPosition->positionId)->toEqual(140357348131)
        ->and($secondPosition->symbolDescription)->toBe('TWTR')
        ->and($secondPosition->pricePaid)->toEqual(0.0)
        ->and($secondPosition->quantity)->toEqual(3.0)
        ->and($secondPosition->positionType)->toBe('LONG')
        ->and($secondPosition->daysGain)->toEqual(-3.915)
        ->and($secondPosition->daysGainPct)->toEqual(-2.8369)
        ->and($secondPosition->marketValue)->toEqual(134.085)
        ->and($secondPosition->pctOfPortfolio)->toEqual(0.0235)
        ->and($secondPosition->quick)->toBeInstanceOf(QuickViewDTO::class)
        ->and($secondPosition->quick->change)->toEqual(-1.305)
        ->and($secondPosition->quick->changePct)->toEqual(-2.8369)
        ->and($secondPosition->quick->lastTrade)->toEqual(44.695)
        ->and($secondPosition->quick->volume)->toEqual(26582141)
        ->and($secondPosition->lotsDetails)->toBe('https://api.etrade.com/v1/accounts/yIFaUoJ81qyAhgxLWRQ42g/portfolio/140357348131')
        ->and($secondPosition->quoteDetails)->toBe('https://api.etrade.com/v1/market/quote/TWTR')
        ->and($secondPosition->product)->toBeInstanceOf(ProductDTO::class)
        ->and($secondPosition->product->symbol)->toBe('TWTR')
        ->and($secondPosition->product->securityType)->toBe('EQ')
        ->and($secondPosition->product->strikePrice)->toEqual(0.0);
});

it('throws exception on non-200 response for view portfolio', function () {
    // Cache an access token
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    // Mock a failing response
    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('get')
        ->once()
        ->andReturn(new Response(500, [], 'Internal Server Error'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $viewPortfolioRequestDto = new ViewPortfolioRequestDTO([
        'accountIdKey' => 'test_account_id_key',
    ]);

    expect(function () use ($etradeClient, $viewPortfolioRequestDto) {
        $etradeClient->getViewPortfolio($viewPortfolioRequestDto);
    })->toThrow(EtradeApiException::class, 'Failed to view account portfolio');
});

it('throws exception if viewing portfolio when no token is cached', function () {
    Cache::forget(config('laravel-etrade.oauth_access_token_key'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $viewPortfolioRequestDto = new ViewPortfolioRequestDTO([
        'accountIdKey' => 'test_account_id_key',
    ]);

    expect(function () use ($etradeClient, $viewPortfolioRequestDto) {
        $etradeClient->getViewPortfolio($viewPortfolioRequestDto);
    })->toThrow(EtradeApiException::class, 'Cached access tokens missing or expired.');
});

it('can get quotes successfully', function () {
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    $xmlResponse = file_get_contents(__DIR__ . '/../fixtures/GetQuotesResponse.xml');
    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('get')
        ->once()
        ->andReturn(new Response(200, [], $xmlResponse));
    $symbol = 'GOOG';

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $getQuotesRequestDto = new GetQuotesRequestDTO([
        'symbols' => [$symbol],
        'detailFlag' => 'ALL',
        'requireEarningsDate' => true,
    ]);

    $quotesResponse = $etradeClient->getQuotes($getQuotesRequestDto);

    expect($quotesResponse)->toBeInstanceOf(GetQuotesResponseDTO::class)
        ->and($quotesResponse->quoteData)->toHaveCount(1);

    $quote = $quotesResponse->quoteData[$symbol];
    expect($quote->dateTime)->toBe('15:17:00 EDT 06-20-2018')
        ->and($quote->dateTimeUTC)->toBe(1529522220)
        ->and($quote->quoteStatus)->toBe('DELAYED')
        ->and($quote->ahFlag)->toBeFalse()
        ->and($quote->hasMiniOptions)->toBeFalse()
        ->and($quote->product->symbol)->toBe('GOOG')
        ->and($quote->product->securityType)->toBe('EQ')
        ->and($quote->all)->not->toBeNull()
        ->and($quote->all->ask)->toBe(1175.79)
        ->and($quote->all->bid)->toBe(1175.29)
        ->and($quote->all->companyName)->toBe('ALPHABET INC CAP STK CL C')
        ->and($quote->all->primaryExchange)->toBe('NSDQ');
});

it('can get multi quotes successfully', function () {
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    $xmlResponse = file_get_contents(__DIR__ . '/../fixtures/GetQuotesMultiResponse.xml');
    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('get')
        ->once()
        ->andReturn(new Response(200, [], $xmlResponse));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $googSymbol = 'GOOG';
    $tslaSymbol = 'TSLA';
    $getQuotesRequestDto = new GetQuotesRequestDTO([
        'symbols' => [$googSymbol, $tslaSymbol],
        'detailFlag' => 'ALL',
        'requireEarningsDate' => true,
    ]);

    $quotesResponse = $etradeClient->getQuotes($getQuotesRequestDto);

    expect($quotesResponse)->toBeInstanceOf(GetQuotesResponseDTO::class)
        ->and($quotesResponse->quoteData)->toHaveCount(2);

    $googQuote = $quotesResponse->quoteData[$googSymbol];
    expect($googQuote->dateTime)->toBe('15:17:00 EDT 06-20-2018')
        ->and($googQuote->dateTimeUTC)->toBe(1529522220)
        ->and($googQuote->quoteStatus)->toBe('DELAYED')
        ->and($googQuote->ahFlag)->toBeFalse()
        ->and($googQuote->hasMiniOptions)->toBeFalse()
        ->and($googQuote->product->symbol)->toBe('GOOG')
        ->and($googQuote->product->securityType)->toBe('EQ')
        ->and($googQuote->all)->not->toBeNull()
        ->and($googQuote->all->ask)->toBe(1175.79)
        ->and($googQuote->all->bid)->toBe(1175.29)
        ->and($googQuote->all->companyName)->toBe('ALPHABET INC CAP STK CL C')
        ->and($googQuote->all->primaryExchange)->toBe('NSDQ')
        ->and($googQuote->fundamental)->not->toBeNull()
        ->and($googQuote->fundamental->symbolDescription)->toBe('ALPHABET INC CAP STK CL C')
        ->and($googQuote->fundamental->eps)->toBe(23.5639)
        ->and($googQuote->fundamental->estEarnings)->toBe(43.981)
        ->and($googQuote->intraday)->not->toBeNull()
        ->and($googQuote->intraday->ask)->toBe(1175.79)
        ->and($googQuote->intraday->bid)->toBe(1175.29)
        ->and($googQuote->intraday->lastTrade)->toBe(1175.74)
        ->and($googQuote->option)->not->toBeNull()
        ->and($googQuote->option->ask)->toBe(10.0)
        ->and($googQuote->option->bid)->toBe(9.8)
        ->and($googQuote->option->lastTrade)->toBe(9.9)
        ->and($googQuote->week52)->not->toBeNull()
        ->and($googQuote->week52->high52)->toBe(1186.89)
        ->and($googQuote->week52->low52)->toBe(894.79)
        ->and($googQuote->mutualFund)->not->toBeNull()
        ->and($googQuote->mutualFund->cusip)->toBe('02079K107')
        ->and($googQuote->mutualFund->fundFamily)->toBe('Alphabet Inc.')
        ->and($googQuote->mutualFund->netAssetValue)->toBe(1175.74);

    $tslaQuote = $quotesResponse->quoteData[$tslaSymbol];
    expect($tslaQuote->dateTime)->toBe('16:00:00 EDT 06-20-2018')
        ->and($tslaQuote->dateTimeUTC)->toBe(1529524800)
        ->and($tslaQuote->quoteStatus)->toBe('REALTIME')
        ->and($tslaQuote->ahFlag)->toBeTrue()
        ->and($tslaQuote->hasMiniOptions)->toBeTrue()
        ->and($tslaQuote->product->symbol)->toBe('TSLA')
        ->and($tslaQuote->product->securityType)->toBe('EQ')
        ->and($tslaQuote->all)->not->toBeNull()
        ->and($tslaQuote->all->ask)->toBe(2150.50)
        ->and($tslaQuote->all->bid)->toBe(2150.00)
        ->and($tslaQuote->all->companyName)->toBe('TESLA INC')
        ->and($tslaQuote->all->primaryExchange)->toBe('NSDQ')
        ->and($tslaQuote->fundamental)->not->toBeNull()
        ->and($tslaQuote->fundamental->symbolDescription)->toBe('TESLA INC')
        ->and($tslaQuote->fundamental->eps)->toBe(5.00)
        ->and($tslaQuote->fundamental->estEarnings)->toBe(7.50)
        ->and($tslaQuote->intraday)->not->toBeNull()
        ->and($tslaQuote->intraday->ask)->toBe(2150.50)
        ->and($tslaQuote->intraday->bid)->toBe(2150.00)
        ->and($tslaQuote->intraday->lastTrade)->toBe(2150.25)
        ->and($tslaQuote->option)->not->toBeNull()
        ->and($tslaQuote->option->ask)->toBe(50.0)
        ->and($tslaQuote->option->bid)->toBe(49.8)
        ->and($tslaQuote->option->lastTrade)->toBe(49.9)
        ->and($tslaQuote->week52)->not->toBeNull()
        ->and($tslaQuote->week52->high52)->toBe(2200.00)
        ->and($tslaQuote->week52->low52)->toBe(350.00)
        ->and($tslaQuote->mutualFund)->not->toBeNull()
        ->and($tslaQuote->mutualFund->cusip)->toBe('88160R101')
        ->and($tslaQuote->mutualFund->fundFamily)->toBe('Tesla, Inc.')
        ->and($tslaQuote->mutualFund->netAssetValue)->toBe(2150.25);
});

it('throws exception if symbols are missing when getting quotes', function () {
    $etradeClient = new EtradeApiClient('test_key', 'test_secret');

    expect(function () use ($etradeClient) {
        $etradeClient->getQuotes(new GetQuotesRequestDTO());
    })->toThrow(EtradeApiException::class, 'symbols is required!');
});

it('throws exception on non-200 response for get quotes', function () {
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('get')
        ->once()
        ->andReturn(new Response(500, [], 'Internal Server Error'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $getQuotesRequestDto = new GetQuotesRequestDTO([
        'symbols' => ['GOOG'],
    ]);

    expect(function () use ($etradeClient, $getQuotesRequestDto) {
        $etradeClient->getQuotes($getQuotesRequestDto);
    })->toThrow(EtradeApiException::class, 'Failed to get quotes');
});

it('throws exception if getting quotes when no token is cached', function () {
    Cache::forget(config('laravel-etrade.oauth_access_token_key'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $getQuotesRequestDto = new GetQuotesRequestDTO([
        'symbols' => ['GOOG'],
    ]);

    expect(function () use ($etradeClient, $getQuotesRequestDto) {
        $etradeClient->getQuotes($getQuotesRequestDto);
    })->toThrow(EtradeApiException::class, 'Cached access tokens missing or expired.');
});

it('can lookup products successfully', function () {
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    $xmlResponse = file_get_contents(__DIR__ . '/../fixtures/LookupResponse.xml');
    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('get')
        ->once()
        ->andReturn(new Response(200, [], $xmlResponse));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $lookupRequestDto = new LookupRequestDTO([
        'search' => 'a',
    ]);

    $lookupResponseDto = $etradeClient->lookupProduct($lookupRequestDto);

    expect($lookupResponseDto)->toBeInstanceOf(LookupResponseDTO::class)
        ->and($lookupResponseDto->data)->toHaveCount(3);

    $first = $lookupResponseDto->data[0];
    expect($first)->toBeInstanceOf(DataDTO::class)
        ->and($first->symbol)->toBe('A')
        ->and($first->description)->toBe('AGILENT TECHNOLOGIES INC COM')
        ->and($first->type)->toBe('EQUITY');
});

it('throws exception if search is missing when looking up products', function () {
    $etradeClient = new EtradeApiClient('test_key', 'test_secret');

    expect(function () use ($etradeClient) {
        $etradeClient->lookupProduct(new LookupRequestDTO());
    })->toThrow(EtradeApiException::class, 'search is required!');
});

it('throws exception on non-200 response for lookup product', function () {
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('get')
        ->once()
        ->andReturn(new Response(500, [], 'Internal Server Error'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $lookupRequestDto = new LookupRequestDTO([
        'search' => 'a',
    ]);

    expect(function () use ($etradeClient, $lookupRequestDto) {
        $etradeClient->lookupProduct($lookupRequestDto);
    })->toThrow(EtradeApiException::class, 'Failed to lookup product');
});

it('throws exception if looking up product when no token is cached', function () {
    Cache::forget(config('laravel-etrade.oauth_access_token_key'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $lookupRequestDto = new LookupRequestDTO([
        'search' => 'a',
    ]);

    expect(function () use ($etradeClient, $lookupRequestDto) {
        $etradeClient->lookupProduct($lookupRequestDto);
    })->toThrow(EtradeApiException::class, 'Cached access tokens missing or expired.');
});

it('can get option chains successfully', function () {
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    $xmlResponse = file_get_contents(__DIR__ . '/../fixtures/OptionChainResponse.xml');
    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('get')
        ->once()
        ->andReturn(new Response(200, [], $xmlResponse));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $getOptionChainsRequestDto = new GetOptionChainsRequestDTO([
        'symbol' => 'IBM',
        'expiryYear' => 2018,
        'expiryMonth' => 8,
        'strikePriceNear' => 200,
        'noOfStrikes' => 2,
    ]);

    $optionChainsResponse = $etradeClient->getOptionChains($getOptionChainsRequestDto);

    expect($optionChainsResponse)->toBeInstanceOf(OptionChainResponseDTO::class)
        ->and($optionChainsResponse->optionPairs)->toHaveCount(2)
        ->and($optionChainsResponse->nearPrice)->toBe(200.0)
        ->and($optionChainsResponse->timeStamp)->toBe(1529430420)
        ->and($optionChainsResponse->quoteType)->toBe('DELAYED')
        ->and($optionChainsResponse->selected)->toBeInstanceOf(SelectedEDDTO::class)
        ->and($optionChainsResponse->selected->day)->toBe(17)
        ->and($optionChainsResponse->selected->month)->toBe(8)
        ->and($optionChainsResponse->selected->year)->toBe(2018);

    $firstPair = $optionChainsResponse->optionPairs[0];
    expect($firstPair)->toBeInstanceOf(OptionChainPairDTO::class)
        ->and($firstPair->call)->toBeInstanceOf(OptionDetailsDTO::class)
        ->and($firstPair->put)->toBeInstanceOf(OptionDetailsDTO::class)
        ->and($firstPair->call->optionGreek)->toBeInstanceOf(OptionGreeksDTO::class)
        ->and($firstPair->call->optionGreek->delta)->toBe(0.0049)
        ->and($firstPair->put->optionGreek->rho)->toBe(-0.2782);
});

it('throws exception if symbol is missing when getting option chains', function () {
    $etradeClient = new EtradeApiClient('test_key', 'test_secret');

    expect(function () use ($etradeClient) {
        $etradeClient->getOptionChains(new GetOptionChainsRequestDTO());
    })->toThrow(EtradeApiException::class, 'symbol is required!');
});

it('throws exception on non-200 response for get option chains', function () {
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('get')
        ->once()
        ->andReturn(new Response(500, [], 'Internal Server Error'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $getOptionChainsRequestDto = new GetOptionChainsRequestDTO([
        'symbol' => 'IBM',
    ]);

    expect(function () use ($etradeClient, $getOptionChainsRequestDto) {
        $etradeClient->getOptionChains($getOptionChainsRequestDto);
    })->toThrow(EtradeApiException::class, 'Failed to get option chains');
});

it('throws exception if getting option chains when no token is cached', function () {
    Cache::forget(config('laravel-etrade.oauth_access_token_key'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $getOptionChainsRequestDto = new GetOptionChainsRequestDTO([
        'symbol' => 'IBM',
    ]);

    expect(function () use ($etradeClient, $getOptionChainsRequestDto) {
        $etradeClient->getOptionChains($getOptionChainsRequestDto);
    })->toThrow(EtradeApiException::class, 'Cached access tokens missing or expired.');
});

it('can get option expire dates successfully', function () {
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    $xmlResponse = file_get_contents(__DIR__ . '/../fixtures/OptionExpireDateResponse.xml');
    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('get')
        ->once()
        ->andReturn(new Response(200, [], $xmlResponse));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $getOptionExpireDatesRequestDto = new GetOptionExpireDatesRequestDTO([
        'symbol' => 'GOOG',
        'expiryType' => 'ALL',
    ]);

    $optionExpireResponse = $etradeClient->getOptionExpireDates($getOptionExpireDatesRequestDto);

    expect($optionExpireResponse)->toBeInstanceOf(OptionExpireDateResponseDTO::class)
        ->and($optionExpireResponse->expirationDates)->toHaveCount(3);

    $firstExpiration = $optionExpireResponse->expirationDates[0];
    expect($firstExpiration)->toBeInstanceOf(ExpirationDateDTO::class)
        ->and($firstExpiration->year)->toBe(2018)
        ->and($firstExpiration->month)->toBe(6)
        ->and($firstExpiration->day)->toBe(22)
        ->and($firstExpiration->expiryType)->toBe('WEEKLY');
});

it('throws exception if symbol is missing when getting option expire dates', function () {
    $etradeClient = new EtradeApiClient('test_key', 'test_secret');

    expect(function () use ($etradeClient) {
        $etradeClient->getOptionExpireDates(new GetOptionExpireDatesRequestDTO());
    })->toThrow(EtradeApiException::class, 'symbol is required!');
});

it('throws exception on non-200 response for get option expire dates', function () {
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('get')
        ->once()
        ->andReturn(new Response(500, [], 'Internal Server Error'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $getOptionExpireDatesRequestDto = new GetOptionExpireDatesRequestDTO([
        'symbol' => 'GOOG',
    ]);

    expect(function () use ($etradeClient, $getOptionExpireDatesRequestDto) {
        $etradeClient->getOptionExpireDates($getOptionExpireDatesRequestDto);
    })->toThrow(EtradeApiException::class, 'Failed to get option expiration dates');
});

it('throws exception if getting option expire dates when no token is cached', function () {
    Cache::forget(config('laravel-etrade.oauth_access_token_key'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $getOptionExpireDatesRequestDto = new GetOptionExpireDatesRequestDTO([
        'symbol' => 'GOOG',
    ]);

    expect(function () use ($etradeClient, $getOptionExpireDatesRequestDto) {
        $etradeClient->getOptionExpireDates($getOptionExpireDatesRequestDto);
    })->toThrow(EtradeApiException::class, 'Cached access tokens missing or expired.');
});

it('can list orders successfully', function () {
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    $xmlResponse = file_get_contents(__DIR__ . '/../fixtures/ListOrdersResponse.xml');
    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('get')
        ->once()
        ->andReturn(new Response(200, [], $xmlResponse));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $listOrdersRequestDto = new ListOrdersRequestDTO([
        'accountIdKey' => 'account-key',
        'count' => 5,
    ]);

    $ordersResponse = $etradeClient->listOrders($listOrdersRequestDto);

    expect($ordersResponse)->toBeInstanceOf(OrdersResponseDTO::class)
        ->and($ordersResponse->order)->toHaveCount(2)
        ->and($ordersResponse->marker)->toBe('abc')
        ->and($ordersResponse->next)->toBe('def')
        ->and($ordersResponse->messages->message)->toHaveCount(1);

    $firstOrder = $ordersResponse->order[0];
    expect($firstOrder)->toBeInstanceOf(OrderDTO::class)
        ->and($firstOrder->orderId)->toBe(96)
        ->and($firstOrder->orderDetail)->toHaveCount(1)
        ->and($firstOrder->events)->toHaveCount(1);

    $firstDetail = $firstOrder->orderDetail[0];
    expect($firstDetail)->toBeInstanceOf(OrderDetailDTO::class)
        ->and($firstDetail->orderNumber)->toBe(123)
        ->and($firstDetail->orderValue)->toBe(1000.0)
        ->and($firstDetail->status)->toBe('EXECUTED')
        ->and($firstDetail->instrument)->toHaveCount(1);

    $instrument = $firstDetail->instrument[0];
    expect($instrument)->toBeInstanceOf(InstrumentDTO::class)
        ->and($instrument->product)->toBeInstanceOf(ListOrdersProductDTO::class)
        ->and($instrument->product->symbol)->toBe('ABC')
        ->and($instrument->quantity)->toBe(100.0)
        ->and($instrument->filledQuantity)->toBe(100.0)
        ->and($instrument->averageExecutionPrice)->toBe(10.0)
        ->and($instrument->estimatedCommission)->toBe(1.5)
        ->and($instrument->estimatedFees)->toBe(0.0);

    $secondOrder = $ordersResponse->order[1];
    $secondDetail = $secondOrder->orderDetail[0];
    $secondInstrument = $secondDetail->instrument[0];
    expect($secondOrder->orderId)->toBe(95)
        ->and($secondInstrument->lots)->toHaveCount(2)
        ->and($secondInstrument->lots[0])->toBeInstanceOf(LotDTO::class)
        ->and($secondInstrument->lots[0]->size)->toBe(25.0);
});

it('can list all orders across pages', function () {
    $firstPage = OrdersResponseDTO::fromXml(file_get_contents(__DIR__ . '/../fixtures/ListOrdersResponse.xml'));
    $secondPage = OrdersResponseDTO::fromXml(file_get_contents(__DIR__ . '/../fixtures/ListOrdersResponsePage2.xml'));

    $receivedMarkers = [];
    $etradeClient = \Mockery::mock(EtradeApiClient::class, ['test_key', 'test_secret'])->makePartial();
    $etradeClient->shouldReceive('listOrders')
        ->twice()
        ->withArgs(function (ListOrdersRequestDTO $dto) use (&$receivedMarkers) {
            $receivedMarkers[] = $dto->marker ?? null;
            return true;
        })
        ->andReturn($firstPage, $secondPage);

    $request = new ListOrdersRequestDTO([
        'accountIdKey' => 'account-key',
        'count' => 2,
    ]);

    $response = $etradeClient->listAllOrders($request);

    expect($receivedMarkers)->toEqual([null, 'abc'])
        ->and($response->order)->toHaveCount(3)
        ->and($response->marker)->toBeNull()
        ->and($response->messages->message)->toHaveCount(1);
});

it('stops paginating when callDepth is reached', function () {
    $firstPage = OrdersResponseDTO::fromXml(file_get_contents(__DIR__ . '/../fixtures/ListOrdersResponse.xml'));

    $etradeClient = \Mockery::mock(EtradeApiClient::class, ['test_key', 'test_secret'])->makePartial();
    $etradeClient->shouldReceive('listOrders')
        ->once()
        ->andReturn($firstPage);

    $request = new ListOrdersRequestDTO([
        'accountIdKey' => 'account-key',
        'count' => 2,
        'callDepth' => 1,
    ]);

    $response = $etradeClient->listAllOrders($request);

    expect($response->order)->toHaveCount(2)
        ->and($response->marker)->toBe('abc');
});

it('throws exception if accountIdKey is missing when listing orders', function () {
    $etradeClient = new EtradeApiClient('test_key', 'test_secret');

    expect(function () use ($etradeClient) {
        $etradeClient->listOrders(new ListOrdersRequestDTO());
    })->toThrow(EtradeApiException::class, 'accountIdKey is required!');
});

it('throws exception on non-200 response for list orders', function () {
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('get')
        ->once()
        ->andReturn(new Response(500, [], 'Internal Server Error'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $listOrdersRequestDto = new ListOrdersRequestDTO([
        'accountIdKey' => 'account-key',
    ]);

    expect(function () use ($etradeClient, $listOrdersRequestDto) {
        $etradeClient->listOrders($listOrdersRequestDto);
    })->toThrow(EtradeApiException::class, 'Failed to list orders');
});

it('throws exception if listing orders when no token is cached', function () {
    Cache::forget(config('laravel-etrade.oauth_access_token_key'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $listOrdersRequestDto = new ListOrdersRequestDTO([
        'accountIdKey' => 'account-key',
    ]);

    expect(function () use ($etradeClient, $listOrdersRequestDto) {
        $etradeClient->listOrders($listOrdersRequestDto);
    })->toThrow(EtradeApiException::class, 'Cached access tokens missing or expired.');
});

it('can preview equity orders successfully', function () {
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    $jsonResponse = file_get_contents(__DIR__ . '/../fixtures/PreviewOrderResponseEquity.json');
    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('post')
        ->once()
        ->andReturn(new Response(200, [], $jsonResponse));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $previewOrderRequestDto = new PreviewOrderRequestDTO([
        'accountIdKey' => 'account-key',
        'orderType' => 'EQ',
        'clientOrderId' => 'client-order-id',
        'order' => [
            [
                'orderTerm' => 'GOOD_FOR_DAY',
                'priceType' => 'LIMIT',
                'instrument' => [
                    [
                        'orderAction' => 'BUY',
                        'quantityType' => 'QUANTITY',
                        'quantity' => 1,
                        'product' => [
                            'symbol' => 'FB',
                            'securityType' => 'EQ',
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $previewResponse = $etradeClient->previewOrder($previewOrderRequestDto);

    expect($previewResponse)->toBeInstanceOf(PreviewOrderResponseDTO::class)
        ->and($previewResponse->orderType)->toBe('EQ')
        ->and($previewResponse->totalOrderValue)->toBe(175.95)
        ->and($previewResponse->previewIds)->toHaveCount(1)
        ->and($previewResponse->previewIds[0]->previewId)->toBe(3429395279);

    $order = $previewResponse->order[0];
    expect($order)->toBeInstanceOf(\KevinRider\LaravelEtrade\Dtos\Orders\OrderDetailDTO::class)
        ->and($order->priceType)->toBe('LIMIT')
        ->and($order->estimatedTotalAmount)->toBe(175.95)
        ->and($order->messages->message)->toHaveCount(2);

    $instrument = $order->instrument[0];
    expect($instrument)->toBeInstanceOf(\KevinRider\LaravelEtrade\Dtos\Orders\InstrumentDTO::class)
        ->and($instrument->product->symbol)->toBe('FB')
        ->and($instrument->product->securityType)->toBe('EQ')
        ->and($instrument->quantity)->toBe(1.0)
        ->and($instrument->reserveOrder)->toBeTrue()
        ->and($previewResponse->disclosure->ehDisclosureFlag)->toBeTrue();

});

it('can preview option orders successfully', function () {
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    $jsonResponse = file_get_contents(__DIR__ . '/../fixtures/PreviewOrderResponseOptions.json');
    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('post')
        ->once()
        ->andReturn(new Response(200, [], $jsonResponse));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $previewOrderRequestDto = new PreviewOrderRequestDTO([
        'accountIdKey' => 'account-key',
        'orderType' => 'OPTN',
        'clientOrderId' => 'client-order-id',
        'order' => [
            [
                'orderTerm' => 'GOOD_FOR_DAY',
                'priceType' => 'MARKET',
                'instrument' => [
                    [
                        'orderAction' => 'BUY_OPEN',
                        'quantity' => 1,
                        'product' => [
                            'symbol' => 'FB',
                            'securityType' => 'OPTN',
                            'callPut' => 'CALL',
                            'expiryYear' => 2018,
                            'expiryMonth' => 12,
                            'expiryDay' => 21,
                            'strikePrice' => 140,
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $previewResponse = $etradeClient->previewOrder($previewOrderRequestDto);

    expect($previewResponse)->toBeInstanceOf(PreviewOrderResponseDTO::class)
        ->and($previewResponse->orderType)->toBe('OPTN')
        ->and($previewResponse->totalOrderValue)->toBe(330.4644)
        ->and($previewResponse->previewTime)->toBe(1544038038415)
        ->and($previewResponse->accountId)->toBe('314497960')
        ->and($previewResponse->marginLevelCd)->toBe('MARGIN_TRADING_ALLOWED')
        ->and($previewResponse->optionLevelCd)->toBe(4)
        ->and($previewResponse->dstFlag)->toBeFalse()
        ->and($previewResponse->previewIds)->toHaveCount(1)
        ->and($previewResponse->previewIds[0]->previewId)->toBe(2785277279);

    $order = $previewResponse->order[0];
    expect($order->priceType)->toBe('MARKET')
        ->and($order->orderTerm)->toBe('GOOD_FOR_DAY')
        ->and($order->limitPrice)->toBe(0.0)
        ->and($order->stopPrice)->toBe(0.0)
        ->and($order->marketSession)->toBe('REGULAR')
        ->and($order->allOrNone)->toBeFalse()
        ->and($order->egQual)->toBe('EG_QUAL_NOT_AN_ELIGIBLE_SECURITY')
        ->and($order->estimatedCommission)->toBe(5.45)
        ->and($order->estimatedTotalAmount)->toBe(330.4644)
        ->and($order->netPrice)->toBe(0.0)
        ->and($order->netBid)->toBe(0.0)
        ->and($order->netAsk)->toBe(0.0)
        ->and($order->gcd)->toBe(0)
        ->and($order->ratio)->toBe('')
        ->and($order->messages)->toBeNull();

    $instrument = $order->instrument[0];
    expect($instrument->product)->toBeInstanceOf(\KevinRider\LaravelEtrade\Dtos\Orders\ProductDTO::class)
        ->and($instrument->product->callPut)->toBe('CALL')
        ->and($instrument->product->strikePrice)->toBe(140.0)
        ->and($instrument->product->productId->symbol)->toBe('FB----210409P00297500')
        ->and($instrument->product->productId->typeCode)->toBe('OPTION')
        ->and($instrument->quantity)->toBe(1.0)
        ->and($instrument->orderAction)->toBe('BUY_OPEN')
        ->and($instrument->quantityType)->toBe('QUANTITY')
        ->and($instrument->cancelQuantity)->toBe(0.0)
        ->and($instrument->osiKey)->toBe('FB----181221C00140000')
        ->and($instrument->reserveOrder)->toBeTrue()
        ->and($instrument->reserveQuantity)->toBe(0.0)
        ->and($instrument->symbolDescription)->toBe("FB Dec 21 '18 \$140 Call")
        ->and($instrument->product->symbol)->toBe('FB')
        ->and($instrument->product->securityType)->toBe('OPTN')
        ->and($instrument->product->expiryYear)->toBe(2018)
        ->and($instrument->product->expiryMonth)->toBe(12)
        ->and($instrument->product->expiryDay)->toBe(21)
        ->and($previewResponse->disclosure->aoDisclosureFlag)->toBeTrue()
        ->and($previewResponse->disclosure->conditionalDisclosureFlag)->toBeTrue();

});

it('can preview spread orders successfully', function () {
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    $jsonResponse = file_get_contents(__DIR__ . '/../fixtures/PreviewOrderResponseSpread.json');
    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('post')
        ->once()
        ->andReturn(new Response(200, [], $jsonResponse));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $previewOrderRequestDto = new PreviewOrderRequestDTO([
        'accountIdKey' => 'account-key',
        'orderType' => 'SPREADS',
        'clientOrderId' => 'client-order-id',
        'order' => [
            [
                'orderTerm' => 'GOOD_FOR_DAY',
                'priceType' => 'NET_DEBIT',
                'instrument' => [
                    [
                        'orderAction' => 'BUY_OPEN',
                        'quantity' => 1,
                        'product' => [
                            'symbol' => 'IBM',
                            'securityType' => 'OPTN',
                            'callPut' => 'CALL',
                            'expiryYear' => 2019,
                            'expiryMonth' => 2,
                            'expiryDay' => 15,
                            'strikePrice' => 130,
                        ],
                    ],
                    [
                        'orderAction' => 'SELL_OPEN',
                        'quantity' => 1,
                        'product' => [
                            'symbol' => 'IBM',
                            'securityType' => 'OPTN',
                            'callPut' => 'CALL',
                            'expiryYear' => 2019,
                            'expiryMonth' => 2,
                            'expiryDay' => 15,
                            'strikePrice' => 131,
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $previewResponse = $etradeClient->previewOrder($previewOrderRequestDto);

    expect($previewResponse)->toBeInstanceOf(PreviewOrderResponseDTO::class)
        ->and($previewResponse->orderType)->toBe('SPREADS')
        ->and($previewResponse->totalOrderValue)->toBe(508.4762)
        ->and($previewResponse->order)->toHaveCount(1)
        ->and($previewResponse->accountId)->toBe('838796270')
        ->and($previewResponse->marginLevelCd)->toBe('MARGIN_TRADING_ALLOWED')
        ->and($previewResponse->previewTime)->toBe(1549316444960)
        ->and($previewResponse->dstFlag)->toBeFalse()
        ->and($previewResponse->previewIds[0]->previewId)->toBe(3429218279);

    $order = $previewResponse->order[0];
    expect($order->priceType)->toBe('NET_DEBIT')
        ->and($order->orderTerm)->toBe('GOOD_FOR_DAY')
        ->and($order->limitPrice)->toBe(5.0)
        ->and($order->stopPrice)->toBe(0.0)
        ->and($order->marketSession)->toBe('REGULAR')
        ->and($order->allOrNone)->toBeFalse()
        ->and($order->messages->message)->toHaveCount(1)
        ->and($order->messages->message[0]->code)->toBe(3041)
        ->and($order->messages->message[0]->description)->toBe('DTBP is negative but RegTBP is positive')
        ->and($order->messages->message[0]->type)->toBe('WARNING')
        ->and($order->egQual)->toBe('EG_QUAL_NOT_AN_ELIGIBLE_SECURITY')
        ->and($order->estimatedCommission)->toBe(8.44)
        ->and($order->estimatedTotalAmount)->toBe(508.4762)
        ->and($order->netPrice)->toBe(0.0)
        ->and($order->netBid)->toBe(0.0)
        ->and($order->netAsk)->toBe(0.0)
        ->and($order->gcd)->toBe(0)
        ->and($order->ratio)->toBe('')
        ->and($order->instrument)->toHaveCount(2);

    $firstLeg = $order->instrument[0];
    $secondLeg = $order->instrument[1];

    expect($firstLeg->orderAction)->toBe('BUY_OPEN')
        ->and($firstLeg->symbolDescription)->toBe("IBM Feb 15 '19 \$130 Call")
        ->and($firstLeg->quantityType)->toBe('QUANTITY')
        ->and($firstLeg->quantity)->toBe(1.0)
        ->and($firstLeg->cancelQuantity)->toBe(0.0)
        ->and($firstLeg->osiKey)->toBe('IBM---190215C00130000')
        ->and($firstLeg->reserveOrder)->toBeTrue()
        ->and($firstLeg->reserveQuantity)->toBe(0.0)
        ->and($firstLeg->product->strikePrice)->toBe(130.0)
        ->and($firstLeg->product->symbol)->toBe('IBM')
        ->and($firstLeg->product->securityType)->toBe('OPTN')
        ->and($firstLeg->product->callPut)->toBe('CALL')
        ->and($firstLeg->product->expiryYear)->toBe(2019)
        ->and($firstLeg->product->expiryMonth)->toBe(2)
        ->and($firstLeg->product->expiryDay)->toBe(15)
        ->and($secondLeg->orderAction)->toBe('SELL_OPEN')
        ->and($secondLeg->symbolDescription)->toBe("IBM Feb 15 '19 \$131 Call")
        ->and($secondLeg->quantityType)->toBe('QUANTITY')
        ->and($secondLeg->quantity)->toBe(1.0)
        ->and($secondLeg->cancelQuantity)->toBe(0.0)
        ->and($secondLeg->osiKey)->toBe('IBM---190215C00131000')
        ->and($secondLeg->reserveOrder)->toBeTrue()
        ->and($secondLeg->reserveQuantity)->toBe(0.0)
        ->and($secondLeg->product->strikePrice)->toBe(131.0)
        ->and($secondLeg->product->symbol)->toBe('IBM')
        ->and($secondLeg->product->securityType)->toBe('OPTN')
        ->and($secondLeg->product->callPut)->toBe('CALL')
        ->and($secondLeg->product->expiryYear)->toBe(2019)
        ->and($secondLeg->product->expiryMonth)->toBe(2)
        ->and($secondLeg->product->expiryDay)->toBe(15);

    expect($previewResponse->disclosure->conditionalDisclosureFlag)->toBeTrue()
        ->and($previewResponse->disclosure->aoDisclosureFlag)->toBeFalse();
});

it('throws exception if preview order payload is missing required values', function () {
    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $previewOrderRequestDto = new PreviewOrderRequestDTO([
        'accountIdKey' => 'account-key',
        'orderType' => 'EQ',
        'clientOrderId' => 'client-order-id',
    ]);

    expect(function () use ($etradeClient, $previewOrderRequestDto) {
        $etradeClient->previewOrder($previewOrderRequestDto);
    })->toThrow(EtradeApiException::class, 'order is required!');
});

it('throws exception on non-200 response for preview order', function () {
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('post')
        ->once()
        ->andReturn(new Response(500, [], 'Internal Server Error'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $previewOrderRequestDto = new PreviewOrderRequestDTO([
        'accountIdKey' => 'account-key',
        'orderType' => 'EQ',
        'clientOrderId' => 'client-order-id',
        'order' => [
            [
                'priceType' => 'LIMIT',
                'orderTerm' => 'GOOD_FOR_DAY',
                'instrument' => [
                    [
                        'orderAction' => 'BUY',
                        'quantity' => 1,
                        'product' => [
                            'symbol' => 'FB',
                            'securityType' => 'EQ',
                        ],
                    ],
                ],
            ],
        ],
    ]);

    expect(function () use ($etradeClient, $previewOrderRequestDto) {
        $etradeClient->previewOrder($previewOrderRequestDto);
    })->toThrow(EtradeApiException::class, 'Failed to preview order');
});

it('can preview changed orders successfully', function () {
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    $jsonResponse = file_get_contents(__DIR__ . '/../fixtures/ChangePreviewOrderResponse.json');
    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('put')
        ->once()
        ->andReturn(new Response(200, [], $jsonResponse));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $previewOrderRequestDto = new PreviewOrderRequestDTO([
        'accountIdKey' => 'account-key',
        'orderId' => 825,
        'orderType' => 'EQ',
        'clientOrderId' => 's453345er333',
        'order' => [
            [
                'allOrNone' => false,
                'priceType' => 'LIMIT',
                'orderTerm' => 'GOOD_FOR_DAY',
                'marketSession' => 'REGULAR',
                'stopPrice' => '',
                'limitPrice' => 65.31,
                'instrument' => [
                    [
                        'orderAction' => 'BUY',
                        'quantityType' => 'QUANTITY',
                        'quantity' => 6,
                        'product' => [
                            'symbol' => 'F',
                            'securityType' => 'EQ',
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $previewResponse = $etradeClient->previewChangeOrder($previewOrderRequestDto);

    expect($previewResponse)->toBeInstanceOf(PreviewOrderResponseDTO::class)
        ->and($previewResponse->orderType)->toBe('EQ')
        ->and($previewResponse->totalOrderValue)->toBe(396.81)
        ->and($previewResponse->previewIds[0]->previewId)->toBe(926244279)
        ->and($previewResponse->accountId)->toBe('835652930')
        ->and($previewResponse->marginLevelCd)->toBe('MARGIN_TRADING_ALLOWED');

    $order = $previewResponse->order[0];
    $instrument = $order->instrument[0];

    expect($order->priceType)->toBe('LIMIT')
        ->and($order->limitPrice)->toBe(65.31)
        ->and($order->estimatedTotalAmount)->toBe(396.81)
        ->and($instrument->product->symbol)->toBe('F')
        ->and($instrument->orderAction)->toBe('BUY')
        ->and($instrument->quantity)->toBe(6.0);
});

it('throws exception if preview change order payload is missing orderId', function () {
    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $previewOrderRequestDto = new PreviewOrderRequestDTO([
        'accountIdKey' => 'account-key',
        'orderType' => 'EQ',
        'clientOrderId' => 's453345er333',
        'order' => [
            [
                'priceType' => 'LIMIT',
                'orderTerm' => 'GOOD_FOR_DAY',
                'instrument' => [
                    [
                        'orderAction' => 'BUY',
                        'quantity' => 1,
                        'product' => [
                            'symbol' => 'F',
                            'securityType' => 'EQ',
                        ],
                    ],
                ],
            ],
        ],
    ]);

    expect(function () use ($etradeClient, $previewOrderRequestDto) {
        $etradeClient->previewChangeOrder($previewOrderRequestDto);
    })->toThrow(EtradeApiException::class, 'orderId is required!');
});

it('throws exception on non-200 response for preview change order', function () {
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('put')
        ->once()
        ->andReturn(new Response(500, [], 'Internal Server Error'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $previewOrderRequestDto = new PreviewOrderRequestDTO([
        'accountIdKey' => 'account-key',
        'orderId' => 825,
        'orderType' => 'EQ',
        'clientOrderId' => 's453345er333',
        'order' => [
            [
                'orderTerm' => 'GOOD_FOR_DAY',
                'priceType' => 'LIMIT',
                'instrument' => [
                    [
                        'orderAction' => 'BUY',
                        'quantity' => 1,
                        'product' => [
                            'symbol' => 'F',
                            'securityType' => 'EQ',
                        ],
                    ],
                ],
            ],
        ],
    ]);

    expect(function () use ($etradeClient, $previewOrderRequestDto) {
        $etradeClient->previewChangeOrder($previewOrderRequestDto);
    })->toThrow(EtradeApiException::class, 'Failed to preview change order');
});

it('throws exception if previewing change order when no token is cached', function () {
    Cache::forget(config('laravel-etrade.oauth_access_token_key'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $previewOrderRequestDto = new PreviewOrderRequestDTO([
        'accountIdKey' => 'account-key',
        'orderId' => 825,
        'orderType' => 'EQ',
        'clientOrderId' => 's453345er333',
        'order' => [
            [
                'orderTerm' => 'GOOD_FOR_DAY',
                'priceType' => 'LIMIT',
                'instrument' => [
                    [
                        'orderAction' => 'BUY',
                        'quantity' => 1,
                        'product' => [
                            'symbol' => 'F',
                            'securityType' => 'EQ',
                        ],
                    ],
                ],
            ],
        ],
    ]);

    expect(function () use ($etradeClient, $previewOrderRequestDto) {
        $etradeClient->previewChangeOrder($previewOrderRequestDto);
    })->toThrow(EtradeApiException::class, 'Cached access tokens missing or expired.');
});

it('can place equity orders successfully', function () {
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    $jsonResponse = file_get_contents(__DIR__ . '/../fixtures/PlaceOrderResponseEquity.json');
    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('post')
        ->once()
        ->andReturn(new Response(200, [], $jsonResponse));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $placeOrderRequestDto = new PlaceOrderRequestDTO([
        'accountIdKey' => 'account-key',
        'orderType' => 'EQ',
        'clientOrderId' => 'client-order-id',
        'previewIds' => [3429395279],
        'order' => [
            [
                'orderTerm' => 'GOOD_FOR_DAY',
                'priceType' => 'LIMIT',
                'instrument' => [
                    [
                        'orderAction' => 'BUY',
                        'quantityType' => 'QUANTITY',
                        'quantity' => 1,
                        'product' => [
                            'symbol' => 'FB',
                            'securityType' => 'EQ',
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $placeResponse = $etradeClient->placeOrder($placeOrderRequestDto);

    expect($placeResponse)->toBeInstanceOf(PlaceOrderResponseDTO::class)
        ->and($placeResponse->orderType)->toBe('EQ')
        ->and($placeResponse->orderIds)->toHaveCount(1)
        ->and($placeResponse->orderIds[0]->orderId)->toBe(485);

    $order = $placeResponse->order[0];
    expect($order->priceType)->toBe('LIMIT')
        ->and($order->messages->message)->toHaveCount(1);

    $instrument = $order->instrument[0];
    expect($instrument->product->symbol)->toBe('FB')
        ->and($instrument->product->securityType)->toBe('EQ');
});

it('can place option orders successfully', function () {
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    $jsonResponse = file_get_contents(__DIR__ . '/../fixtures/PlaceOrderResponseOptions.json');
    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('post')
        ->once()
        ->andReturn(new Response(200, [], $jsonResponse));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $placeOrderRequestDto = new PlaceOrderRequestDTO([
        'accountIdKey' => 'account-key',
        'orderType' => 'OPTN',
        'clientOrderId' => 'client-order-id',
        'previewIds' => [2785277279],
        'order' => [
            [
                'orderTerm' => 'GOOD_FOR_DAY',
                'priceType' => 'MARKET',
                'instrument' => [
                    [
                        'orderAction' => 'BUY_OPEN',
                        'quantity' => 1,
                        'product' => [
                            'symbol' => 'FB',
                            'securityType' => 'OPTN',
                            'callPut' => 'CALL',
                            'expiryYear' => 2018,
                            'expiryMonth' => 12,
                            'expiryDay' => 21,
                            'strikePrice' => 140,
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $placeResponse = $etradeClient->placeOrder($placeOrderRequestDto);

    expect($placeResponse)->toBeInstanceOf(PlaceOrderResponseDTO::class)
        ->and($placeResponse->orderType)->toBe('OPTN')
        ->and($placeResponse->placedTime)->toBe(1544038195663)
        ->and($placeResponse->accountId)->toBe('314497960')
        ->and($placeResponse->dstFlag)->toBeFalse()
        ->and($placeResponse->marginLevelCd)->toBe('MARGIN_TRADING_ALLOWED')
        ->and($placeResponse->optionLevelCd)->toBe(4)
        ->and($placeResponse->orderIds[0]->orderId)->toBe(169);

    $order = $placeResponse->order[0];
    $instrument = $order->instrument[0];

    expect($instrument->product->callPut)->toBe('CALL')
        ->and($instrument->product->productId->symbol)->toBe('FB----210409P00297500')
        ->and($instrument->product->productId->typeCode)->toBe('OPTION')
        ->and($instrument->product->symbol)->toBe('FB')
        ->and($instrument->product->securityType)->toBe('OPTN')
        ->and($instrument->product->expiryYear)->toBe(2018)
        ->and($instrument->product->expiryMonth)->toBe(12)
        ->and($instrument->product->expiryDay)->toBe(21)
        ->and($instrument->product->strikePrice)->toBe(140.0)
        ->and($instrument->symbolDescription)->toBe("FB Dec 21 '18 \$140 Call")
        ->and($instrument->orderAction)->toBe('BUY_OPEN')
        ->and($instrument->quantityType)->toBe('QUANTITY')
        ->and($instrument->quantity)->toBe(1.0)
        ->and($instrument->cancelQuantity)->toBe(0.0)
        ->and($instrument->osiKey)->toBe('FB----181221C00140000')
        ->and($instrument->reserveOrder)->toBeTrue()
        ->and($instrument->reserveQuantity)->toBe(0.0)
        ->and($order->orderTerm)->toBe('GOOD_FOR_DAY')
        ->and($order->priceType)->toBe('MARKET')
        ->and($order->limitPrice)->toBe(0.0)
        ->and($order->stopPrice)->toBe(0.0)
        ->and($order->marketSession)->toBe('REGULAR')
        ->and($order->allOrNone)->toBeFalse()
        ->and($order->egQual)->toBe('EG_QUAL_NOT_AN_ELIGIBLE_SECURITY')
        ->and($order->estimatedCommission)->toBe(5.45)
        ->and($order->estimatedTotalAmount)->toBe(330.4644)
        ->and($order->netPrice)->toBe(0.0)
        ->and($order->netBid)->toBe(0.0)
        ->and($order->netAsk)->toBe(0.0)
        ->and($order->gcd)->toBe(0)
        ->and($order->ratio)->toBe('')
        ->and($order->messages->message)->toHaveCount(1)
        ->and($order->messages->message[0]->code)->toBe(1026)
        ->and($order->messages->message[0]->type)->toBe('WARNING')
        ->and($order->messages->message[0]->description)->toContain('successfully entered during market hours.');

});

it('can place spread orders successfully', function () {
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    $jsonResponse = file_get_contents(__DIR__ . '/../fixtures/PlaceOrderResponseSpread.json');
    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('post')
        ->once()
        ->andReturn(new Response(200, [], $jsonResponse));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $placeOrderRequestDto = new PlaceOrderRequestDTO([
        'accountIdKey' => 'account-key',
        'orderType' => 'SPREADS',
        'clientOrderId' => 'client-order-id',
        'previewIds' => [3429218279],
        'order' => [
            [
                'orderTerm' => 'GOOD_FOR_DAY',
                'priceType' => 'NET_DEBIT',
                'instrument' => [
                    [
                        'orderAction' => 'BUY_OPEN',
                        'quantity' => 1,
                        'product' => [
                            'symbol' => 'IBM',
                            'securityType' => 'OPTN',
                            'callPut' => 'CALL',
                            'expiryYear' => 2019,
                            'expiryMonth' => 2,
                            'expiryDay' => 15,
                            'strikePrice' => 130,
                        ],
                    ],
                    [
                        'orderAction' => 'SELL_OPEN',
                        'quantity' => 1,
                        'product' => [
                            'symbol' => 'IBM',
                            'securityType' => 'OPTN',
                            'callPut' => 'CALL',
                            'expiryYear' => 2019,
                            'expiryMonth' => 2,
                            'expiryDay' => 15,
                            'strikePrice' => 131,
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $placeResponse = $etradeClient->placeOrder($placeOrderRequestDto);

    expect($placeResponse)->toBeInstanceOf(PlaceOrderResponseDTO::class)
        ->and($placeResponse->orderType)->toBe('SPREADS')
        ->and($placeResponse->accountId)->toBe('838796270')
        ->and($placeResponse->optionLevelCd)->toBe(4)
        ->and($placeResponse->dstFlag)->toBeFalse()
        ->and($placeResponse->marginLevelCd)->toBe('MARGIN_TRADING_ALLOWED')
        ->and($placeResponse->placedTime)->toBe(1549316465349)
        ->and($placeResponse->orderIds[0]->orderId)->toBe(484);

    $order = $placeResponse->order[0];
    expect($order->instrument)->toHaveCount(2)
        ->and($order->instrument[0]->product->strikePrice)->toBe(130.0)
        ->and($order->instrument[1]->product->strikePrice)->toBe(131.0)
        ->and($order->orderTerm)->toBe('GOOD_FOR_DAY')
        ->and($order->priceType)->toBe('NET_DEBIT')
        ->and($order->limitPrice)->toBe(5.0)
        ->and($order->stopPrice)->toBe(0.0)
        ->and($order->marketSession)->toBe('REGULAR')
        ->and($order->allOrNone)->toBeFalse()
        ->and($order->egQual)->toBe('EG_QUAL_NOT_AN_ELIGIBLE_SECURITY')
        ->and($order->estimatedCommission)->toBe(8.44)
        ->and($order->estimatedTotalAmount)->toBe(508.4762)
        ->and($order->netPrice)->toBe(0.0)
        ->and($order->netBid)->toBe(0.0)
        ->and($order->netAsk)->toBe(0.0)
        ->and($order->gcd)->toBe(0)
        ->and($order->ratio)->toBe('')
        ->and($order->messages->message)->toHaveCount(1)
        ->and($order->messages->message[0]->code)->toBe(1027)
        ->and($order->messages->message[0]->type)->toBe('WARNING')
        ->and($order->messages->message[0]->description)->toContain('market was closed when we received your order');

    $firstLeg = $order->instrument[0];
    $secondLeg = $order->instrument[1];

    expect($firstLeg->symbolDescription)->toBe("IBM Feb 15 '19 \$130 Call")
        ->and($firstLeg->orderAction)->toBe('BUY_OPEN')
        ->and($firstLeg->quantityType)->toBe('QUANTITY')
        ->and($firstLeg->quantity)->toBe(1.0)
        ->and($firstLeg->cancelQuantity)->toBe(0.0)
        ->and($firstLeg->osiKey)->toBe('IBM---190215C00130000')
        ->and($firstLeg->reserveOrder)->toBeTrue()
        ->and($firstLeg->reserveQuantity)->toBe(0.0)
        ->and($firstLeg->product->symbol)->toBe('IBM')
        ->and($firstLeg->product->securityType)->toBe('OPTN')
        ->and($firstLeg->product->callPut)->toBe('CALL')
        ->and($firstLeg->product->expiryYear)->toBe(2019)
        ->and($firstLeg->product->expiryMonth)->toBe(2)
        ->and($firstLeg->product->expiryDay)->toBe(15)
        ->and($secondLeg->symbolDescription)->toBe("IBM Feb 15 '19 \$131 Call")
        ->and($secondLeg->orderAction)->toBe('SELL_OPEN')
        ->and($secondLeg->quantityType)->toBe('QUANTITY')
        ->and($secondLeg->quantity)->toBe(1.0)
        ->and($secondLeg->cancelQuantity)->toBe(0.0)
        ->and($secondLeg->osiKey)->toBe('IBM---190215C00131000')
        ->and($secondLeg->reserveOrder)->toBeTrue()
        ->and($secondLeg->reserveQuantity)->toBe(0.0)
        ->and($secondLeg->product->symbol)->toBe('IBM')
        ->and($secondLeg->product->securityType)->toBe('OPTN')
        ->and($secondLeg->product->callPut)->toBe('CALL')
        ->and($secondLeg->product->expiryYear)->toBe(2019)
        ->and($secondLeg->product->expiryMonth)->toBe(2)
        ->and($secondLeg->product->expiryDay)->toBe(15);

});

it('throws exception if place order payload is missing required values', function () {
    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $placeOrderRequestDto = new PlaceOrderRequestDTO([
        'accountIdKey' => 'account-key',
        'orderType' => 'EQ',
        'clientOrderId' => 'client-order-id',
        'order' => [
            [
                'orderTerm' => 'GOOD_FOR_DAY',
            ],
        ],
    ]);

    expect(function () use ($etradeClient, $placeOrderRequestDto) {
        $etradeClient->placeOrder($placeOrderRequestDto);
    })->toThrow(EtradeApiException::class, 'previewIds is required!');
});

it('throws exception on non-200 response for place order', function () {
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('post')
        ->once()
        ->andReturn(new Response(500, [], 'Internal Server Error'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $placeOrderRequestDto = new PlaceOrderRequestDTO([
        'accountIdKey' => 'account-key',
        'orderType' => 'EQ',
        'clientOrderId' => 'client-order-id',
        'previewIds' => [123],
        'order' => [
            [
                'orderTerm' => 'GOOD_FOR_DAY',
                'instrument' => [
                    [
                        'orderAction' => 'BUY',
                        'quantity' => 1,
                        'product' => [
                            'symbol' => 'FB',
                            'securityType' => 'EQ',
                        ],
                    ],
                ],
            ],
        ],
    ]);

    expect(function () use ($etradeClient, $placeOrderRequestDto) {
        $etradeClient->placeOrder($placeOrderRequestDto);
    })->toThrow(EtradeApiException::class, 'Failed to place order');
});

it('can place changed orders successfully', function () {
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    $jsonResponse = file_get_contents(__DIR__ . '/../fixtures/PlaceChangeOrderResponse.json');
    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('put')
        ->once()
        ->andReturn(new Response(200, [], $jsonResponse));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $placeOrderRequestDto = new PlaceOrderRequestDTO([
        'accountIdKey' => 'account-key',
        'orderId' => 825,
        'orderType' => 'EQ',
        'clientOrderId' => 's453dddff5er333',
        'previewIds' => [926244279],
        'order' => [
            [
                'allOrNone' => false,
                'priceType' => 'LIMIT',
                'orderTerm' => 'GOOD_FOR_DAY',
                'marketSession' => 'REGULAR',
                'stopPrice' => '',
                'limitPrice' => 65.31,
                'instrument' => [
                    [
                        'orderAction' => 'BUY',
                        'quantityType' => 'QUANTITY',
                        'quantity' => 6,
                        'product' => [
                            'symbol' => 'F',
                            'securityType' => 'EQ',
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $placeResponse = $etradeClient->placeChangeOrder($placeOrderRequestDto);

    expect($placeResponse)->toBeInstanceOf(PlaceOrderResponseDTO::class)
        ->and($placeResponse->orderType)->toBe('EQ')
        ->and($placeResponse->orderIds[0]->orderId)->toBe(826)
        ->and($placeResponse->accountId)->toBe('835652930')
        ->and($placeResponse->dstFlag)->toBeTrue();

    $order = $placeResponse->order[0];
    $instrument = $order->instrument[0];

    expect($order->limitPrice)->toBe(65.31)
        ->and($order->estimatedTotalAmount)->toBe(396.81)
        ->and($instrument->product->symbol)->toBe('F')
        ->and($instrument->orderAction)->toBe('BUY')
        ->and($instrument->quantity)->toBe(6.0);
});

it('throws exception if place change order payload is missing orderId', function () {
    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $placeOrderRequestDto = new PlaceOrderRequestDTO([
        'accountIdKey' => 'account-key',
        'orderType' => 'EQ',
        'clientOrderId' => 'client-order-id',
        'previewIds' => [123],
        'order' => [
            [
                'priceType' => 'LIMIT',
                'orderTerm' => 'GOOD_FOR_DAY',
                'instrument' => [
                    [
                        'orderAction' => 'BUY',
                        'quantity' => 1,
                        'product' => [
                            'symbol' => 'F',
                            'securityType' => 'EQ',
                        ],
                    ],
                ],
            ],
        ],
    ]);

    expect(function () use ($etradeClient, $placeOrderRequestDto) {
        $etradeClient->placeChangeOrder($placeOrderRequestDto);
    })->toThrow(EtradeApiException::class, 'orderId is required!');
});

it('throws exception on non-200 response for place change order', function () {
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('put')
        ->once()
        ->andReturn(new Response(500, [], 'Internal Server Error'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $placeOrderRequestDto = new PlaceOrderRequestDTO([
        'accountIdKey' => 'account-key',
        'orderId' => 825,
        'orderType' => 'EQ',
        'clientOrderId' => 'client-order-id',
        'previewIds' => [123],
        'order' => [
            [
                'orderTerm' => 'GOOD_FOR_DAY',
                'priceType' => 'LIMIT',
                'instrument' => [
                    [
                        'orderAction' => 'BUY',
                        'quantity' => 1,
                        'product' => [
                            'symbol' => 'F',
                            'securityType' => 'EQ',
                        ],
                    ],
                ],
            ],
        ],
    ]);

    expect(function () use ($etradeClient, $placeOrderRequestDto) {
        $etradeClient->placeChangeOrder($placeOrderRequestDto);
    })->toThrow(EtradeApiException::class, 'Failed to place change order');
});

it('throws exception if placing change order when no token is cached', function () {
    Cache::forget(config('laravel-etrade.oauth_access_token_key'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $placeOrderRequestDto = new PlaceOrderRequestDTO([
        'accountIdKey' => 'account-key',
        'orderId' => 825,
        'orderType' => 'EQ',
        'clientOrderId' => 'client-order-id',
        'previewIds' => [123],
        'order' => [
            [
                'orderTerm' => 'GOOD_FOR_DAY',
                'priceType' => 'LIMIT',
                'instrument' => [
                    [
                        'orderAction' => 'BUY',
                        'quantity' => 1,
                        'product' => [
                            'symbol' => 'F',
                            'securityType' => 'EQ',
                        ],
                    ],
                ],
            ],
        ],
    ]);

    expect(function () use ($etradeClient, $placeOrderRequestDto) {
        $etradeClient->placeChangeOrder($placeOrderRequestDto);
    })->toThrow(EtradeApiException::class, 'Cached access tokens missing or expired.');
});

it('can cancel orders successfully', function () {
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    $jsonResponse = file_get_contents(__DIR__ . '/../fixtures/CancelOrderResponse.json');
    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('put')
        ->once()
        ->andReturn(new Response(200, [], $jsonResponse));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $cancelOrderRequestDto = new CancelOrderRequestDTO([
        'accountIdKey' => 'account-key',
        'orderId' => 11,
    ]);

    $cancelResponse = $etradeClient->cancelOrder($cancelOrderRequestDto);

    expect($cancelResponse)->toBeInstanceOf(CancelOrderResponseDTO::class)
        ->and($cancelResponse->accountId)->toBe('634386170')
        ->and($cancelResponse->orderId)->toBe(11)
        ->and($cancelResponse->cancelTime)->toBe(1529563499081)
        ->and($cancelResponse->messages->message[0]->code)->toBe(5011)
        ->and($cancelResponse->messages->message[0]->description)->toContain('request to cancel your order is being processed.')
        ->and($cancelResponse->messages->message[0]->type)->toBe('WARNING');
});

it('throws exception if cancel order payload is missing required values', function () {
    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $cancelOrderRequestDto = new CancelOrderRequestDTO([
        'orderId' => 11,
    ]);

    expect(function () use ($etradeClient, $cancelOrderRequestDto) {
        $etradeClient->cancelOrder($cancelOrderRequestDto);
    })->toThrow(EtradeApiException::class, 'accountIdKey is required!');

    $cancelOrderRequestDto = new CancelOrderRequestDTO([
        'accountIdKey' => 'account-key',
    ]);

    expect(function () use ($etradeClient, $cancelOrderRequestDto) {
        $etradeClient->cancelOrder($cancelOrderRequestDto);
    })->toThrow(EtradeApiException::class, 'orderId is required!');
});

it('throws exception on non-200 response for cancel order', function () {
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('put')
        ->once()
        ->andReturn(new Response(500, [], 'Internal Server Error'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $cancelOrderRequestDto = new CancelOrderRequestDTO([
        'accountIdKey' => 'account-key',
        'orderId' => 11,
    ]);

    expect(function () use ($etradeClient, $cancelOrderRequestDto) {
        $etradeClient->cancelOrder($cancelOrderRequestDto);
    })->toThrow(EtradeApiException::class, 'Failed to cancel order');
});

it('throws exception if canceling order when no token is cached', function () {
    Cache::forget(config('laravel-etrade.oauth_access_token_key'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $cancelOrderRequestDto = new CancelOrderRequestDTO([
        'accountIdKey' => 'account-key',
        'orderId' => 11,
    ]);

    expect(function () use ($etradeClient, $cancelOrderRequestDto) {
        $etradeClient->cancelOrder($cancelOrderRequestDto);
    })->toThrow(EtradeApiException::class, 'Cached access tokens missing or expired.');
});

it('can list alerts successfully', function () {
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    $xmlResponse = file_get_contents(__DIR__ . '/../fixtures/ListAlertsResponse.xml');
    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('get')
        ->once()
        ->andReturn(new Response(200, [], $xmlResponse));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $listAlertsRequestDto = new ListAlertsRequestDTO([
        'count' => 3,
        'status' => 'UNREAD',
    ]);

    $alertsDto = $etradeClient->getAlerts($listAlertsRequestDto);

    expect($alertsDto)->toBeInstanceOf(ListAlertsResponseDTO::class)
        ->and($alertsDto->totalAlerts)->toEqual(148)
        ->and($alertsDto->alerts)->toHaveCount(9);

    $firstAlert = $alertsDto->alerts[0];
    expect($firstAlert)->toBeInstanceOf(AlertDTO::class)
        ->and($firstAlert->id)->toEqual(6774)
        ->and($firstAlert->status)->toBe('UNREAD')
        ->and($firstAlert->subject)->toBe('Transfer failed-Insufficient Funds');
});

it('throws exception on non-200 response when listing alerts', function () {
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('get')
        ->once()
        ->andReturn(new Response(500, [], 'Internal Server Error'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');

    expect(function () use ($etradeClient) {
        $etradeClient->getAlerts(new ListAlertsRequestDTO());
    })->toThrow(EtradeApiException::class, 'Failed to list alerts');
});

it('can list alert details successfully', function () {
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    $xmlResponse = file_get_contents(__DIR__ . '/../fixtures/ListAlertDetailsResponse.xml');
    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('get')
        ->once()
        ->andReturn(new Response(200, [], $xmlResponse));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $listAlertDetailsRequestDto = new ListAlertDetailsRequestDTO([
        'alertId' => 6773,
        'htmlTags' => true,
    ]);

    $alertDetailsDto = $etradeClient->getAlertDetails($listAlertDetailsRequestDto);

    expect($alertDetailsDto)->toBeInstanceOf(ListAlertDetailsResponseDTO::class)
        ->and($alertDetailsDto->subject)->toBe('AAPL down by at least 2.00%')
        ->and($alertDetailsDto->symbol)->toBe('AAPL')
        ->and($alertDetailsDto->next)->toBe('https://api.etrade.com/v1/user/alerts/6772')
        ->and($alertDetailsDto->prev)->toBe('https://api.etrade.com/v1/user/alerts/6774');
});

it('throws exception if alert id is missing when requesting alert details', function () {
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');

    expect(function () use ($etradeClient) {
        $etradeClient->getAlertDetails(new ListAlertDetailsRequestDTO());
    })->toThrow(EtradeApiException::class, 'alertId is required!');
});

it('throws exception on non-200 response when requesting alert details', function () {
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('get')
        ->once()
        ->andReturn(new Response(500, [], 'Internal Server Error'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $listAlertDetailsRequestDto = new ListAlertDetailsRequestDTO([
        'alertId' => 6773,
    ]);

    expect(function () use ($etradeClient, $listAlertDetailsRequestDto) {
        $etradeClient->getAlertDetails($listAlertDetailsRequestDto);
    })->toThrow(EtradeApiException::class, 'Failed to get alert details');
});

it('can delete alerts successfully', function () {
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    $xmlResponse = file_get_contents(__DIR__ . '/../fixtures/DeleteAlertsResponse.xml');
    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('delete')
        ->once()
        ->andReturn(new Response(200, [], $xmlResponse));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $deleteAlertRequestDto = new DeleteAlertsRequestDTO([
        'alertIds' => [6772, 6774],
    ]);

    $deleteAlertsDto = $etradeClient->deleteAlerts($deleteAlertRequestDto);

    expect($deleteAlertsDto)->toBeInstanceOf(DeleteAlertsResponseDTO::class)
        ->and($deleteAlertsDto->result)->toBe('SUCCESS')
        ->and($deleteAlertsDto->failedAlerts)->toBeInstanceOf(FailedAlertsDTO::class)
        ->and($deleteAlertsDto->failedAlerts->alertId)->toEqual([6772, 6774]);
});

it('throws exception if delete alerts has no alert ids', function () {
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');

    expect(function () use ($etradeClient) {
        $etradeClient->deleteAlerts(new DeleteAlertsRequestDTO());
    })->toThrow(EtradeApiException::class, 'At least one alertId is required!');
});

it('throws exception on non-200 response when deleting alerts', function () {
    $accessToken = [
        'oauth_token' => 'test_access_token',
        'oauth_token_secret' => 'test_access_token_secret',
        'inactive_at' => now()->addHour()->getTimestamp(),
    ];
    Cache::put(
        config('laravel-etrade.oauth_access_token_key'),
        Crypt::encryptString(json_encode($accessToken)),
        Carbon::createFromTime(23, 59, 59, 'America/New_York')
    );

    $mockGuzzleClient = \Mockery::mock('overload:GuzzleHttp\\Client');
    $mockGuzzleClient->shouldReceive('delete')
        ->once()
        ->andReturn(new Response(500, [], 'Internal Server Error'));

    $etradeClient = new EtradeApiClient('test_key', 'test_secret');
    $deleteAlertRequestDto = new DeleteAlertsRequestDTO([
        'alertIds' => [6772,6774],
    ]);

    expect(function () use ($etradeClient, $deleteAlertRequestDto) {
        $etradeClient->deleteAlerts($deleteAlertRequestDto);
    })->toThrow(EtradeApiException::class, 'Failed to delete alerts');
});
