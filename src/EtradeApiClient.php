<?php

namespace KevinRider\LaravelEtrade;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Subscriber\Oauth\Oauth1;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use KevinRider\LaravelEtrade\Dtos\Response\AccountBalanceResponseDTO;
use KevinRider\LaravelEtrade\Dtos\Response\AccountListResponseDTO;
use KevinRider\LaravelEtrade\Dtos\Response\ListAlertDetailsResponseDTO;
use KevinRider\LaravelEtrade\Dtos\Response\ListAlertsResponseDTO;
use KevinRider\LaravelEtrade\Dtos\AuthorizationUrlDTO;
use KevinRider\LaravelEtrade\Dtos\Response\CancelOrderResponseDTO;
use KevinRider\LaravelEtrade\Dtos\Response\GetQuotesResponseDTO;
use KevinRider\LaravelEtrade\Dtos\Response\DeleteAlertsResponseDTO;
use KevinRider\LaravelEtrade\Dtos\EtradeAccessTokenDTO;
use KevinRider\LaravelEtrade\Dtos\Response\ListTransactionDetailsResponseDTO;
use KevinRider\LaravelEtrade\Dtos\Response\ListTransactionsResponseDTO;
use KevinRider\LaravelEtrade\Dtos\Response\LookupResponseDTO;
use KevinRider\LaravelEtrade\Dtos\Response\OrdersResponseDTO;
use KevinRider\LaravelEtrade\Dtos\Response\OptionChainResponseDTO;
use KevinRider\LaravelEtrade\Dtos\Response\OptionExpireDateResponseDTO;
use KevinRider\LaravelEtrade\Dtos\Response\PlaceOrderResponseDTO;
use KevinRider\LaravelEtrade\Dtos\Response\PreviewOrderResponseDTO;
use KevinRider\LaravelEtrade\Dtos\Request\AccountBalanceRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\DeleteAlertsRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\ListAlertDetailsRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\ListAlertsRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\CancelOrderRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\ListOrdersRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\OrderRequestBaseDTO;
use KevinRider\LaravelEtrade\Dtos\Request\PlaceOrderRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\ListTransactionDetailsRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\ListTransactionsRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\LookupRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\GetQuotesRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\GetOptionChainsRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\GetOptionExpireDatesRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\PreviewOrderRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\ViewPortfolioRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Response\ViewPortfolioResponseDTO;
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
     * @return bool
     */
    public function isProduction(): bool
    {
        return $this->isProduction;
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
            throw new EtradeApiException(
                'Failed to get request token',
                $response->getStatusCode(),
                $response->getHeaders(),
                null,
                'GET',
                EtradeConfig::OAUTH_REQUEST_TOKEN
            );
        }

        parse_str($response->getBody()->getContents(), $token);

        if (!isset($token['oauth_token']) || !isset($token['oauth_token_secret'])) {
            throw new EtradeApiException(
                'Malformed get request token response',
                $response->getStatusCode(),
                $response->getHeaders(),
                null,
                'GET',
                EtradeConfig::OAUTH_REQUEST_TOKEN
            );
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
        $oauthTokenArray = $this->getTokenInCache(
            config('laravel-etrade.oauth_request_token_key'),
            'Request tokens missing or expired.'
        );

        $this->client = $this->createOauthClient([
            'token' => $oauthTokenArray['oauth_token'],
            'token_secret' => $oauthTokenArray['oauth_token_secret'],
            'verifier' => $verifierCode,
        ]);

        $response = $this->client->get(EtradeConfig::OAUTH_ACCESS_TOKEN);

        if ($response->getStatusCode() !== 200) {
            throw new EtradeApiException(
                'Failed to get access token',
                $response->getStatusCode(),
                $response->getHeaders(),
                null,
                'GET',
                EtradeConfig::OAUTH_ACCESS_TOKEN
            );
        }

        parse_str($response->getBody()->getContents(), $accessToken);

        if (!isset($accessToken['oauth_token']) || !isset($accessToken['oauth_token_secret'])) {
            throw new EtradeApiException(
                'Malformed get access token response',
                $response->getStatusCode(),
                $response->getHeaders(),
                null,
                'GET',
                EtradeConfig::OAUTH_ACCESS_TOKEN
            );
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

        if ($response->getStatusCode() !== 200) {
            throw new EtradeApiException(
                'Failed to renew access token',
                $response->getStatusCode(),
                $response->getHeaders(),
                null,
                'GET',
                EtradeConfig::OAUTH_RENEW_ACCESS_TOKEN
            );
        }
        $accessToken['oauth_token'] = $accessTokenDTO->oauthToken;
        $accessToken['oauth_token_secret'] = $accessTokenDTO->oauthTokenSecret;
        $twoHoursFromNow = now()->addHours(2);
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
     * @return void
     * @throws EtradeApiException
     * @throws GuzzleException
     */
    public function revokeAccessToken(): void
    {
        $accessTokenDTO = $this->getCachedAccessToken();
        $this->client = $this->createOauthClient([
            'token' => $accessTokenDTO->oauthToken,
            'token_secret' => $accessTokenDTO->oauthTokenSecret,
        ]);
        $response = $this->client->get(EtradeConfig::OAUTH_REVOKE_ACCESS_TOKEN);
        if ($response->getStatusCode() !== 200) {
            throw new EtradeApiException(
                'Failed to revoke access token',
                $response->getStatusCode(),
                $response->getHeaders(),
                null,
                'GET',
                EtradeConfig::OAUTH_REVOKE_ACCESS_TOKEN
            );
        }
        $this->deleteAccessTokenInCache();
    }

    /**
     * @return AccountListResponseDTO
     * @throws EtradeApiException
     * @throws GuzzleException
     */
    public function getAccountList(): AccountListResponseDTO
    {
        $this->setAuthenticatedClient();

        return $this->requestAndParse(
            'get',
            EtradeConfig::ACCOUNTS_LIST,
            [],
            'Failed to get account list',
            fn (string $body) => AccountListResponseDTO::fromXml($body)
        );
    }

    /**
     * @param AccountBalanceRequestDTO $accountBalanceRequestDTO
     * @return AccountBalanceResponseDTO
     * @throws EtradeApiException
     * @throws GuzzleException
     */
    public function getAccountBalance(AccountBalanceRequestDTO $accountBalanceRequestDTO): AccountBalanceResponseDTO
    {
        if (empty($accountBalanceRequestDTO->accountIdKey)) {
            throw new EtradeApiException('accountIdKey is required!');
        }

        $this->setAuthenticatedClient();

        $uri = str_replace('{accountIdKey}', $accountBalanceRequestDTO->accountIdKey, EtradeConfig::ACCOUNTS_BALANCE);

        $queryParams = $this->buildQueryParams(AccountBalanceRequestDTO::ALLOWED_QUERY_PARAMS, $accountBalanceRequestDTO);

        return $this->requestAndParse(
            'get',
            $uri,
            ['query' => $queryParams],
            'Failed to get account balance',
            fn (string $body) => AccountBalanceResponseDTO::fromXml($body)
        );
    }

    /**
     * @param ListTransactionsRequestDTO $listTransactionsRequestDTO
     * @return ListTransactionsResponseDTO
     * @throws EtradeApiException
     * @throws GuzzleException
     */
    public function getAccountTransactions(ListTransactionsRequestDTO $listTransactionsRequestDTO): ListTransactionsResponseDTO
    {
        if (empty($listTransactionsRequestDTO->accountIdKey)) {
            throw new EtradeApiException('accountIdKey is required!');
        }

        $this->setAuthenticatedClient();

        $uri = str_replace('{accountIdKey}', $listTransactionsRequestDTO->accountIdKey, EtradeConfig::ACCOUNTS_TRANSACTIONS);

        $queryParams = $this->buildQueryParams(ListTransactionsRequestDTO::ALLOWED_QUERY_PARAMS, $listTransactionsRequestDTO);

        return $this->requestAndParse(
            'get',
            $uri,
            ['query' => $queryParams],
            'Failed to get account transactions',
            fn (string $body) => ListTransactionsResponseDTO::fromXml($body)
        );
    }

    /**
     * @param ListTransactionDetailsRequestDTO $listTransactionDetailsRequestDTO
     * @return ListTransactionDetailsResponseDTO
     * @throws EtradeApiException
     * @throws GuzzleException
     */
    public function getAccountTransactionDetails(ListTransactionDetailsRequestDTO $listTransactionDetailsRequestDTO): ListTransactionDetailsResponseDTO
    {
        if (empty($listTransactionDetailsRequestDTO->accountIdKey)) {
            throw new EtradeApiException('accountIdKey is required!');
        }
        if (empty($listTransactionDetailsRequestDTO->transactionId)) {
            throw new EtradeApiException('transactionId is required!');
        }

        $this->setAuthenticatedClient();

        $uri = str_replace(['{accountIdKey}', '{transactionId}'], [$listTransactionDetailsRequestDTO->accountIdKey, $listTransactionDetailsRequestDTO->transactionId], EtradeConfig::ACCOUNTS_TRANSACTIONS_DETAILS);

        $queryParams = $this->buildQueryParams(ListTransactionDetailsRequestDTO::ALLOWED_QUERY_PARAMS, $listTransactionDetailsRequestDTO);

        return $this->requestAndParse(
            'get',
            $uri,
            ['query' => $queryParams],
            'Failed to get account transaction details',
            fn (string $body) => ListTransactionDetailsResponseDTO::fromXml($body)
        );
    }

    /**
     * @param ViewPortfolioRequestDTO $viewPortfolioRequestDTO
     * @return ViewPortfolioResponseDTO
     * @throws EtradeApiException
     * @throws GuzzleException
     */
    public function getViewPortfolio(ViewPortfolioRequestDTO $viewPortfolioRequestDTO): ViewPortfolioResponseDTO
    {
        if (empty($viewPortfolioRequestDTO->accountIdKey)) {
            throw new EtradeApiException('accountIdKey is required!');
        }

        $this->setAuthenticatedClient();

        $uri = str_replace('{accountIdKey}', $viewPortfolioRequestDTO->accountIdKey, EtradeConfig::ACCOUNTS_PORTFOLIO);

        $queryParams = $this->buildQueryParams(ViewPortfolioRequestDTO::ALLOWED_QUERY_PARAMS, $viewPortfolioRequestDTO);

        return $this->requestAndParse(
            'get',
            $uri,
            ['query' => $queryParams],
            'Failed to view account portfolio',
            fn (string $body) => ViewPortfolioResponseDTO::fromXml($body)
        );
    }

    /**
     * @param ListAlertsRequestDTO $listAlertsRequestDTO
     * @return ListAlertsResponseDTO
     * @throws EtradeApiException
     * @throws GuzzleException
     */
    public function getAlerts(ListAlertsRequestDTO $listAlertsRequestDTO): ListAlertsResponseDTO
    {
        $this->setAuthenticatedClient();

        $queryParams = $this->buildQueryParams(ListAlertsRequestDTO::ALLOWED_QUERY_PARAMS, $listAlertsRequestDTO);

        return $this->requestAndParse(
            'get',
            EtradeConfig::ALERTS_LIST,
            ['query' => $queryParams],
            'Failed to list alerts',
            fn (string $body) => ListAlertsResponseDTO::fromXml($body)
        );
    }

    /**
     * @param ListAlertDetailsRequestDTO $listAlertDetailsRequestDTO
     * @return ListAlertDetailsResponseDTO
     * @throws EtradeApiException
     * @throws GuzzleException
     */
    public function getAlertDetails(ListAlertDetailsRequestDTO $listAlertDetailsRequestDTO): ListAlertDetailsResponseDTO
    {
        if (empty($listAlertDetailsRequestDTO->alertId)) {
            throw new EtradeApiException('alertId is required!');
        }

        $this->setAuthenticatedClient();

        $uri = str_replace('{alertId}', $listAlertDetailsRequestDTO->alertId, EtradeConfig::ALERTS_DETAILS);

        $queryParams = $this->buildQueryParams(ListAlertDetailsRequestDTO::ALLOWED_QUERY_PARAMS, $listAlertDetailsRequestDTO);

        return $this->requestAndParse(
            'get',
            $uri,
            ['query' => $queryParams],
            'Failed to get alert details',
            fn (string $body) => ListAlertDetailsResponseDTO::fromXml($body)
        );
    }

    /**
     * @param DeleteAlertsRequestDTO $deleteAlertRequestDTO
     * @return DeleteAlertsResponseDTO
     * @throws EtradeApiException
     * @throws GuzzleException
     */
    public function deleteAlerts(DeleteAlertsRequestDTO $deleteAlertRequestDTO): DeleteAlertsResponseDTO
    {
        $alertIdPathSegment = $deleteAlertRequestDTO->getAlertIdsPathSegment();
        if (!$alertIdPathSegment) {
            throw new EtradeApiException('At least one alertId is required!');
        }

        $this->setAuthenticatedClient();

        $uri = str_replace('{alertId}', $alertIdPathSegment, EtradeConfig::ALERTS_DELETE);

        return $this->requestAndParse(
            'delete',
            $uri,
            [],
            'Failed to delete alerts',
            fn (string $body) => DeleteAlertsResponseDTO::fromXml($body)
        );
    }

    /**
     * @param ListOrdersRequestDTO $listOrdersRequestDTO
     * @return OrdersResponseDTO
     * @throws EtradeApiException
     * @throws GuzzleException
     */
    public function listOrders(ListOrdersRequestDTO $listOrdersRequestDTO): OrdersResponseDTO
    {
        if (empty($listOrdersRequestDTO->accountIdKey)) {
            throw new EtradeApiException('accountIdKey is required!');
        }

        $this->setAuthenticatedClient();

        $uri = str_replace('{accountIdKey}', $listOrdersRequestDTO->accountIdKey, EtradeConfig::ORDER_LIST);

        $queryParams = $this->buildQueryParams(ListOrdersRequestDTO::ALLOWED_QUERY_PARAMS, $listOrdersRequestDTO);

        return $this->requestAndParse(
            'get',
            $uri,
            ['query' => $queryParams],
            'Failed to list orders',
            fn (string $body) => OrdersResponseDTO::fromXml($body)
        );
    }

    /**
     * @param ListOrdersRequestDTO $listOrdersRequestDTO
     * @return OrdersResponseDTO
     * @throws EtradeApiException
     * @throws GuzzleException
     */
    public function listAllOrders(ListOrdersRequestDTO $listOrdersRequestDTO): OrdersResponseDTO
    {
        $callDepth = $listOrdersRequestDTO->callDepth ?? 10;
        $calls = 0;
        $orders = [];
        do {
            $calls++;
            $lastResponse = $this->listOrders($listOrdersRequestDTO);
            $orders = array_merge($orders, $lastResponse->order);
            $marker = $lastResponse->marker ?? null;
            $listOrdersRequestDTO->marker = $marker;
        } while ($marker && $calls < $callDepth);

        $response = new OrdersResponseDTO();
        $response->order = $orders;
        $response->marker = $lastResponse->marker;
        $response->next = $lastResponse->next;
        $response->messages = $lastResponse->messages;

        return $response;
    }

    /**
     * @param PreviewOrderRequestDTO $previewOrderRequestDTO
     * @return PreviewOrderResponseDTO
     * @throws EtradeApiException
     * @throws GuzzleException
     */
    public function previewOrder(PreviewOrderRequestDTO $previewOrderRequestDTO): PreviewOrderResponseDTO
    {
        foreach (PreviewOrderRequestDTO::REQUIRED_PROPERTIES as $requiredProperty) {
            if (empty($previewOrderRequestDTO->$requiredProperty)) {
                throw new EtradeApiException($requiredProperty . ' is required!');
            }
        }

        $this->setAuthenticatedClient();

        $uri = str_replace('{accountIdKey}', $previewOrderRequestDTO->accountIdKey, EtradeConfig::ORDER_PREVIEW);

        return $this->requestAndParse(
            'post',
            $uri,
            ['json' => $previewOrderRequestDTO->toRequestBody()],
            'Failed to preview order',
            fn (string $body) => PreviewOrderResponseDTO::fromJson($body)
        );
    }

    /**
     * @param PlaceOrderRequestDTO $placeOrderRequestDTO
     * @return PlaceOrderResponseDTO
     * @throws EtradeApiException
     * @throws GuzzleException
     */
    public function placeOrder(PlaceOrderRequestDTO $placeOrderRequestDTO): PlaceOrderResponseDTO
    {
        foreach (PlaceOrderRequestDTO::REQUIRED_PROPERTIES as $requiredProperty) {
            if (empty($placeOrderRequestDTO->$requiredProperty)) {
                throw new EtradeApiException($requiredProperty . ' is required!');
            }
        }

        $this->setAuthenticatedClient();

        $uri = str_replace('{accountIdKey}', $placeOrderRequestDTO->accountIdKey, EtradeConfig::ORDER_PLACE);

        return $this->requestAndParse(
            'post',
            $uri,
            ['json' => $placeOrderRequestDTO->toRequestBody()],
            'Failed to place order',
            fn (string $body) => PlaceOrderResponseDTO::fromJson($body)
        );
    }

    /**
     * @param PreviewOrderRequestDTO $previewOrderRequestDTO
     * @return PreviewOrderResponseDTO
     * @throws EtradeApiException
     * @throws GuzzleException
     */
    public function previewChangeOrder(PreviewOrderRequestDTO $previewOrderRequestDTO): PreviewOrderResponseDTO
    {
        $this->validateRequiredProperties($previewOrderRequestDTO, [...PreviewOrderRequestDTO::REQUIRED_PROPERTIES, 'orderId']);

        return $this->sendChangeOrderRequest(
            $previewOrderRequestDTO,
            EtradeConfig::ORDER_CHANGE_PREVIEW,
            $previewOrderRequestDTO->toRequestBody(),
            fn (string $body) => PreviewOrderResponseDTO::fromJson($body),
            'Failed to preview change order'
        );
    }

    /**
     * @param PlaceOrderRequestDTO $placeOrderRequestDTO
     * @return PlaceOrderResponseDTO
     * @throws EtradeApiException
     * @throws GuzzleException
     */
    public function placeChangeOrder(PlaceOrderRequestDTO $placeOrderRequestDTO): PlaceOrderResponseDTO
    {
        $this->validateRequiredProperties($placeOrderRequestDTO, [...PlaceOrderRequestDTO::REQUIRED_PROPERTIES, 'orderId']);

        return $this->sendChangeOrderRequest(
            $placeOrderRequestDTO,
            EtradeConfig::ORDER_PLACE_CHANGE,
            $placeOrderRequestDTO->toRequestBody(),
            fn (string $body) => PlaceOrderResponseDTO::fromJson($body),
            'Failed to place change order'
        );
    }

    /**
     * @param CancelOrderRequestDTO $cancelOrderRequestDTO
     * @return CancelOrderResponseDTO
     * @throws EtradeApiException
     * @throws GuzzleException
     */
    public function cancelOrder(CancelOrderRequestDTO $cancelOrderRequestDTO): CancelOrderResponseDTO
    {
        if (empty($cancelOrderRequestDTO->accountIdKey)) {
            throw new EtradeApiException('accountIdKey is required!');
        }

        if (empty($cancelOrderRequestDTO->orderId)) {
            throw new EtradeApiException('orderId is required!');
        }

        $this->setAuthenticatedClient();

        $uri = str_replace('{accountIdKey}', $cancelOrderRequestDTO->accountIdKey, EtradeConfig::ORDER_CANCEL);

        return $this->requestAndParse(
            'put',
            $uri,
            ['json' => $cancelOrderRequestDTO->toRequestBody()],
            'Failed to cancel order',
            fn (string $body) => CancelOrderResponseDTO::fromJson($body)
        );
    }

    /**
     * @param LookupRequestDTO $lookupRequestDTO
     * @return LookupResponseDTO
     * @throws EtradeApiException
     * @throws GuzzleException
     */
    public function lookupProduct(LookupRequestDTO $lookupRequestDTO): LookupResponseDTO
    {
        if (empty($lookupRequestDTO->search)) {
            throw new EtradeApiException('search is required!');
        }

        $this->setAuthenticatedClient();

        $uri = str_replace('{search}', $lookupRequestDTO->search, EtradeConfig::MARKET_LOOKUP);

        return $this->requestAndParse(
            'get',
            $uri,
            [],
            'Failed to lookup product',
            fn (string $body) => LookupResponseDTO::fromXml($body)
        );
    }

    /**
     * @param GetOptionChainsRequestDTO $getOptionChainsRequestDTO
     * @return OptionChainResponseDTO
     * @throws EtradeApiException
     * @throws GuzzleException
     */
    public function getOptionChains(GetOptionChainsRequestDTO $getOptionChainsRequestDTO): OptionChainResponseDTO
    {
        if (empty($getOptionChainsRequestDTO->symbol)) {
            throw new EtradeApiException('symbol is required!');
        }

        $this->setAuthenticatedClient();

        $queryParams = $this->buildQueryParams(GetOptionChainsRequestDTO::ALLOWED_QUERY_PARAMS, $getOptionChainsRequestDTO);

        return $this->requestAndParse(
            'get',
            EtradeConfig::MARKET_OPTION_CHAINS,
            ['query' => $queryParams],
            'Failed to get option chains',
            fn (string $body) => OptionChainResponseDTO::fromXml($body)
        );
    }

    /**
     * @param GetOptionExpireDatesRequestDTO $getOptionExpireDatesRequestDTO
     * @return OptionExpireDateResponseDTO
     * @throws EtradeApiException
     * @throws GuzzleException
     */
    public function getOptionExpireDates(GetOptionExpireDatesRequestDTO $getOptionExpireDatesRequestDTO): OptionExpireDateResponseDTO
    {
        if (empty($getOptionExpireDatesRequestDTO->symbol)) {
            throw new EtradeApiException('symbol is required!');
        }

        $this->setAuthenticatedClient();

        $queryParams = $this->buildQueryParams(GetOptionExpireDatesRequestDTO::ALLOWED_QUERY_PARAMS, $getOptionExpireDatesRequestDTO);

        return $this->requestAndParse(
            'get',
            EtradeConfig::MARKET_OPTION_EXPIRY,
            ['query' => $queryParams],
            'Failed to get option expiration dates',
            fn (string $body) => OptionExpireDateResponseDTO::fromXml($body)
        );
    }

    /**
     * @param GetQuotesRequestDTO $getQuotesRequestDTO
     * @return GetQuotesResponseDTO
     * @throws EtradeApiException
     * @throws GuzzleException
     */
    public function getQuotes(GetQuotesRequestDTO $getQuotesRequestDTO): GetQuotesResponseDTO
    {
        if (empty($getQuotesRequestDTO->symbols)) {
            throw new EtradeApiException('symbols is required!');
        }

        $this->setAuthenticatedClient();

        $uri = str_replace('{symbols}', $getQuotesRequestDTO->getSymbols(), EtradeConfig::MARKET_QUOTES);

        $queryParams = $this->buildQueryParams(GetQuotesRequestDTO::ALLOWED_QUERY_PARAMS, $getQuotesRequestDTO);

        return $this->requestAndParse(
            'get',
            $uri,
            ['query' => $queryParams],
            'Failed to get quotes',
            fn (string $body) => GetQuotesResponseDTO::fromXml($body)
        );
    }

    /**
     * @return EtradeAccessTokenDTO
     * @throws EtradeApiException
     */
    protected function getCachedAccessToken(): EtradeAccessTokenDTO
    {
        $oauthTokenArray = $this->getTokenInCache(
            config('laravel-etrade.oauth_access_token_key'),
            'Cached access tokens missing or expired.'
        );
        return new EtradeAccessTokenDTO([
            'oauthToken' => $oauthTokenArray['oauth_token'],
            'oauthTokenSecret' => $oauthTokenArray['oauth_token_secret'],
            'inactiveAt' => Carbon::createFromTimestamp($oauthTokenArray['inactive_at']),
        ]);
    }

    /**
     * @param string $key
     * @param string $exceptionMessage
     * @return array
     * @throws EtradeApiException
     */
    private function getTokenInCache(string $key, string $exceptionMessage): array
    {
        $encryptedTokenArray = Cache::get($key);
        if (!$encryptedTokenArray) {
            throw new EtradeApiException($exceptionMessage);
        }
        $decoded = json_decode(Crypt::decryptString($encryptedTokenArray), true);
        if (!is_array($decoded)) {
            throw new EtradeApiException($exceptionMessage);
        }

        return $decoded;
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
            'auth' => 'oauth',
            'http_errors' => false,
        ]);
    }

    /**
     * @param object $dto
     * @param array $requiredProperties
     * @return void
     * @throws EtradeApiException
     */
    private function validateRequiredProperties(object $dto, array $requiredProperties): void
    {
        foreach ($requiredProperties as $requiredProperty) {
            if (empty($dto->$requiredProperty)) {
                throw new EtradeApiException($requiredProperty . ' is required!');
            }
        }
    }

    /**
     * @param OrderRequestBaseDTO $dto
     * @param string $uriTemplate
     * @param array $payload
     * @param callable $responseParser
     * @param string $errorMessage
     * @return mixed
     * @throws EtradeApiException
     * @throws GuzzleException
     */
    private function sendChangeOrderRequest(
        OrderRequestBaseDTO $dto,
        string $uriTemplate,
        array $payload,
        callable $responseParser,
        string $errorMessage
    ): mixed {
        $this->setAuthenticatedClient();

        $uri = str_replace(
            ['{accountIdKey}', '{orderId}'],
            [$dto->accountIdKey, $dto->orderId],
            $uriTemplate
        );

        return $this->requestAndParse(
            'put',
            $uri,
            ['json' => $payload],
            $errorMessage,
            $responseParser
        );
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

    /**
     * @return void
     */
    private function deleteAccessTokenInCache(): void
    {
        Cache::forget(config('laravel-etrade.oauth_access_token_key'));
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function normalizeQueryParamValue(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return $value;
    }

    /**
     * @return void
     * @throws EtradeApiException
     * @throws GuzzleException
     */
    private function setAuthenticatedClient(): void
    {
        $accessTokenDTO = $this->getAccessToken();

        $this->client = $this->createOauthClient([
            'token' => $accessTokenDTO->oauthToken,
            'token_secret' => $accessTokenDTO->oauthTokenSecret,
        ]);
    }

    /**
     * @param array $allowedParams
     * @param object $dto
     * @return array
     */
    private function buildQueryParams(array $allowedParams, object $dto): array
    {
        $queryParams = [];
        foreach ($allowedParams as $param) {
            if (isset($dto->$param)) {
                $queryParams[$param] = $this->normalizeQueryParamValue($dto->$param);
            }
        }

        return $queryParams;
    }

    /**
     * @param string $method
     * @param string $uri
     * @param array $options
     * @param string $errorMessage
     * @param callable $parser
     * @return mixed
     * @throws EtradeApiException
     * @throws GuzzleException
     */
    private function requestAndParse(
        string $method,
        string $uri,
        array $options,
        string $errorMessage,
        callable $parser
    ): mixed {
        $response = $this->client->{$method}($uri, $options);
        $body = $response->getBody()->getContents();

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new EtradeApiException(
                $errorMessage,
                $response->getStatusCode(),
                $response->getHeaders(),
                $body,
                strtoupper($method),
                $uri
            );
        }

        return $parser($body);
    }
}
