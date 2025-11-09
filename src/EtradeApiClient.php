<?php

namespace KevinRider\LaravelEtrade;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Subscriber\Oauth\Oauth1;
use KevinRider\LaravelEtrade\Dtos\AuthorizationUrlDTO;
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
        $stack = HandlerStack::create();

        $middleware = new Oauth1([
            'consumer_key'    => $this->appKey,
            'consumer_secret' => $this->appSecret,
            'callback' => 'oob',
        ]);

        $stack->push($middleware);

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'handler' => $stack,
            'auth' => 'oauth'
        ]);

        $response = $this->client->get(EtradeConfig::OAUTH_REQUEST_TOKEN);

        if ($response->getStatusCode() !== 200) {
            throw new EtradeApiException('Failed to get request token');
        }

        parse_str($response->getBody()->getContents(), $token);

        if (!isset($token['oauth_token']) && !isset($token['oauth_token_secret'])) {
            throw new EtradeApiException('Malformed get request token response');
        }

        Cache::put(
            config('laravel-etrade.oauth_request_token_key'),
            Crypt::encryptString(json_encode(
                [
                    'oauth_token' => $token['oauth_token'],
                    'oauth_token_secret' => $token['oauth_token_secret'],
                ]
            )),
            now()->addMinutes(5),
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
        if(!$encryptedTokenArray) {
            throw new EtradeApiException('Request tokens missing or expired.');
        } else {
            $oauthTokenArray = json_decode(Crypt::decryptString($encryptedTokenArray), true);
            $oauthToken = $oauthTokenArray['oauth_token'];
            $oauthTokenSecret = $oauthTokenArray['oauth_token_secret'];
        }

        $stack = HandlerStack::create();

        $middleware = new Oauth1([
            'consumer_key' => $this->appKey,
            'consumer_secret' => $this->appSecret,
            'token' => $oauthToken,
            'token_secret' => $oauthTokenSecret,
            'verifier' => $verifierCode,
        ]);

        $stack->push($middleware);

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'handler' => $stack,
            'auth' => 'oauth'
        ]);

        $response = $this->client->get(EtradeConfig::OAUTH_ACCESS_TOKEN);

        if ($response->getStatusCode() !== 200) {
            throw new EtradeApiException('Failed to get access token');
        }

        parse_str($response->getBody()->getContents(), $accessToken);

        if (!isset($accessToken['oauth_token']) && !isset($accessToken['oauth_token_secret'])) {
            throw new EtradeApiException('Malformed get access token response');
        }

        $accessToken['inactive_at'] = now()->addHour(2)->getTimestamp();
        Cache::put(
            config('laravel-etrade.oauth_access_token_key'),
            Crypt::encryptString(json_encode($accessToken)),
            Carbon::createFromTime(23, 59, 59, 'America/New_York')
        );
    }
}
