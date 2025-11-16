<?php

use GuzzleHttp\Psr7\Response;
use KevinRider\LaravelEtrade\Dtos\AccountBalance\CashDTO;
use KevinRider\LaravelEtrade\Dtos\AccountBalance\ComputedBalanceDTO;
use KevinRider\LaravelEtrade\Dtos\AccountBalance\MarginDTO;
use KevinRider\LaravelEtrade\Dtos\AccountBalanceResponseDTO;
use KevinRider\LaravelEtrade\Dtos\ListTransactionDetailsResponseDTO;
use KevinRider\LaravelEtrade\Dtos\ListTransactionsResponseDTO;
use KevinRider\LaravelEtrade\Dtos\Request\AccountBalanceRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\ListTransactionDetailsRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\ListTransactionsRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Transaction\BrokerageDTO;
use KevinRider\LaravelEtrade\Dtos\Transaction\CategoryDTO;
use KevinRider\LaravelEtrade\EtradeApiClient;
use KevinRider\LaravelEtrade\Dtos\AuthorizationUrlDTO;
use KevinRider\LaravelEtrade\Dtos\EtradeAccessTokenDTO;
use KevinRider\LaravelEtrade\EtradeConfig;
use KevinRider\LaravelEtrade\Exceptions\EtradeApiException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Carbon;

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
