<?php

use GuzzleHttp\Psr7\Response;
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
