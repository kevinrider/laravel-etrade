<?php

namespace KevinRider\LaravelEtrade;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Subscriber\Oauth\Oauth1;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use KevinRider\LaravelEtrade\Exceptions\EtradeApiException;

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
     * @return array{authorizationUrl: string, oauthToken: string, oauthTokenSecret: string}
     * @throws EtradeApiException
     * @throws GuzzleException
     */
    public function getAuthorizationUrl(): array
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

        return [
            'authorizationUrl' => EtradeConfig::AUTHORIZE_URL . '?key=' . $this->appKey . '&token=' . $token['oauth_token'],
            'oauthToken' => $token['oauth_token'],
            'oauthTokenSecret' => $token['oauth_token_secret'],
        ];
    }
}