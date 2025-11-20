<?php

use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use KevinRider\LaravelEtrade\Dtos\AccountBalance\CashDTO;
use KevinRider\LaravelEtrade\Dtos\AccountBalance\ComputedBalanceDTO;
use KevinRider\LaravelEtrade\Dtos\AccountBalance\MarginDTO;
use KevinRider\LaravelEtrade\Dtos\AccountBalanceResponseDTO;
use KevinRider\LaravelEtrade\Dtos\ListAlertDetailsResponseDTO;
use KevinRider\LaravelEtrade\Dtos\Alerts\AlertDTO;
use KevinRider\LaravelEtrade\Dtos\Alerts\FailedAlertsDTO;
use KevinRider\LaravelEtrade\Dtos\ListAlertsResponseDTO;
use KevinRider\LaravelEtrade\Dtos\AuthorizationUrlDTO;
use KevinRider\LaravelEtrade\Dtos\DeleteAlertsResponseDTO;
use KevinRider\LaravelEtrade\Dtos\EtradeAccessTokenDTO;
use KevinRider\LaravelEtrade\Dtos\ListTransactionDetailsResponseDTO;
use KevinRider\LaravelEtrade\Dtos\ListTransactionsResponseDTO;
use KevinRider\LaravelEtrade\Dtos\Request\AccountBalanceRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\DeleteAlertsRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\ListAlertDetailsRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\ListAlertsRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\ListTransactionDetailsRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\ListTransactionsRequestDTO;
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
        ->andReturn(new Response(200, [], 'failure'));

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
        ->andReturn(new Response(200, [], 'failure'));

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
