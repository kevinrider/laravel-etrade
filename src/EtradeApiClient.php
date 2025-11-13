<?php

namespace KevinRider\LaravelEtrade;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Subscriber\Oauth\Oauth1;
use KevinRider\LaravelEtrade\Dtos\AuthorizationUrlDTO;
use KevinRider\LaravelEtrade\Dtos\EtradeAccessTokenDTO;
use KevinRider\LaravelEtrade\Exceptions\EtradeApiException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Carbon;

class EtradeApiClient
{
    protected Client $client;
    protected string $baseUrl;

    public function __construct(
        protected string $appKey,
        protected string $appSecret,
        protected bool $isProduction = false
    ) {
        $this->baseUrl = $this->isProduction ? EtradeConfig::LIVE_BASE_URL : EtradeConfig::SANDBOX_BASE_URL;
    }

    /**
     * @return AuthorizationUrlDTO
     * @throws EtradeApiException
     * @throws GuzzleException
     */
    public function getAuthorizationUrl(): AuthorizationUrlDTO
    {
        $this->client = $this->createOauthClient(['callback' => 'oob']);

        $response = $this->client->get(EtradeConfig::OAUTH_REQUEST_TOKEN);

        if ($response->getStatusCode() !== 200) {
            throw new EtradeApiException('Failed to get request token');
        }

        parse_str($response->getBody()->getContents(), $token);

        if (!isset($token['oauth_token']) && !isset($token['oauth_token_secret'])) {
            throw new EtradeApiException('Malformed get request token response');
        }

        $this->storeTokenInCache(
            config('laravel-etrade.oauth_request_token_key'),
            [
                'oauth_token' => $token['oauth_token'],
                'oauth_token_secret' => $token['oauth_token_secret'],
            ],
            now()->addMinutes(5)
        );

        return new AuthorizationUrlDTO([
            'authorizationUrl' => EtradeConfig::AUTHORIZE_URL . '?key=' . $this->appKey . '&token=' . $token['oauth_token'],
            'oauthToken' => $token['oauth_token'],
        ]);
    }

    /**
     * Access tokens hard expire at midnight EST
     * https://apisb.etrade.com/docs/api/authorization/get_access_token.html
     * @param string $verifierCode
     * @return void
     * @throws EtradeApiException
     * @throws GuzzleException
     */
    public function requestAccessTokenAndStore(string $verifierCode): void
    {
        $encryptedTokenArray = Cache::get(config('laravel-etrade.oauth_request_token_key'));
        if (!$encryptedTokenArray) {
            throw new EtradeApiException('Request tokens missing or expired.');
        }
        $oauthTokenArray = json_decode(Crypt::decryptString($encryptedTokenArray), true);
        $oauthToken = $oauthTokenArray['oauth_token'];
        $oauthTokenSecret = $oauthTokenArray['oauth_token_secret'];

        $this->client = $this->createOauthClient([
            'token' => $oauthToken,
            'token_secret' => $oauthTokenSecret,
            'verifier' => $verifierCode,
        ]);

        $response = $this->client->get(EtradeConfig::OAUTH_ACCESS_TOKEN);

        if ($response->getStatusCode() !== 200) {
            throw new EtradeApiException('Failed to get access token');
        }

        parse_str($response->getBody()->getContents(), $accessToken);

        if (!isset($accessToken['oauth_token']) && !isset($accessToken['oauth_token_secret'])) {
            throw new EtradeApiException('Malformed get access token response');
        }

        $accessToken['inactive_at'] = now()->addHours(2)->getTimestamp();
        $this->storeTokenInCache(
            config('laravel-etrade.oauth_access_token_key'),
            $accessToken,
            Carbon::createFromTime(23, 59, 59, 'America/New_York')
        );
    }

    /**
     * @return EtradeAccessTokenDTO
     * @throws EtradeApiException
     * @throws GuzzleException
     */
    public function getAccessToken(): EtradeAccessTokenDTO
    {
        $accessTokenDto = $this->getCachedAccessToken();
        if (now()->getTimestamp() > ($accessTokenDto->inactiveAt->getTimestamp() - config('laravel-etrade.inactive_buffer_in_seconds'))) {
            $accessTokenDto = $this->renewAccessToken($accessTokenDto);
        }
        return $accessTokenDto;
    }

    /**
     * @param EtradeAccessTokenDTO|null $accessTokenDTO
     * @return EtradeAccessTokenDTO
     * @throws EtradeApiException
     * @throws GuzzleException
     */
    public function renewAccessToken(?EtradeAccessTokenDTO $accessTokenDTO = null): EtradeAccessTokenDTO
    {
        if (!$accessTokenDTO) {
            $accessTokenDTO = $this->getCachedAccessToken();
        }

        $this->client = $this->createOauthClient([
            'token' => $accessTokenDTO->oauthToken,
            'token_secret' => $accessTokenDTO->oauthTokenSecret,
        ]);

        $response = $this->client->get(EtradeConfig::OAUTH_RENEW_ACCESS_TOKEN);

        if ($response->getStatusCode() !== 200 || $response->getBody()->getContents() != EtradeConfig::OAUTH_RENEW_ACCESS_TOKEN_SUCCESS) {
            throw new EtradeApiException('Failed to renew access token');
        }
        $accessToken['oauth_token'] = $accessTokenDTO->oauthToken;
        $accessToken['oauth_token_secret'] = $accessTokenDTO->oauthTokenSecret;
        $twoHoursFromNow = now()->addHour(2);
        $accessToken['inactive_at'] = $twoHoursFromNow->getTimestamp();
        $accessTokenDTO->inactiveAt = $twoHoursFromNow;

        $this->storeTokenInCache(
            config('laravel-etrade.oauth_access_token_key'),
            $accessToken,
            Carbon::createFromTime(23, 59, 59, 'America/New_York')
        );

        return $accessTokenDTO;
    }

    /**
     * @return EtradeAccessTokenDTO
     * @throws EtradeApiException
     */
    protected function getCachedAccessToken(): EtradeAccessTokenDTO
    {
        $encryptedTokenArray = Cache::get(config('laravel-etrade.oauth_access_token_key'));
        if (!$encryptedTokenArray) {
            throw new EtradeApiException('Cached access tokens missing or expired.');
        }
        $oauthTokenArray = json_decode(Crypt::decryptString($encryptedTokenArray), true);
        return new EtradeAccessTokenDTO([
            'oauthToken' => $oauthTokenArray['oauth_token'],
            'oauthTokenSecret' => $oauthTokenArray['oauth_token_secret'],
            'inactiveAt' => Carbon::createFromTimestamp($oauthTokenArray['inactive_at']),
        ]);
    }

    /**
     * @param array $oauthParams
     * @return Client
     */
    private function createOauthClient(array $oauthParams = []): Client
    {
        $stack = HandlerStack::create();

        $middleware = new Oauth1(array_merge([
            'consumer_key'    => $this->appKey,
            'consumer_secret' => $this->appSecret,
        ], $oauthParams));

        $stack->push($middleware);

        return new Client([
            'base_uri' => $this->baseUrl,
            'handler' => $stack,
            'auth' => 'oauth'
        ]);
    }

    /**
     * @param string $key
     * @param array $tokenData
     * @param Carbon $expiresAt
     * @return void
     */
    private function storeTokenInCache(string $key, array $tokenData, Carbon $expiresAt): void
    {
        Cache::put(
            $key,
            Crypt::encryptString(json_encode($tokenData)),
            $expiresAt
        );
    }
}
