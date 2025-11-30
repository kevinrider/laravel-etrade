<?php

namespace KevinRider\LaravelEtrade;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Subscriber\Oauth\Oauth1;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use KevinRider\LaravelEtrade\Dtos\AccountBalanceResponseDTO;
use KevinRider\LaravelEtrade\Dtos\AccountListResponseDTO;
use KevinRider\LaravelEtrade\Dtos\ListAlertDetailsResponseDTO;
use KevinRider\LaravelEtrade\Dtos\ListAlertsResponseDTO;
use KevinRider\LaravelEtrade\Dtos\AuthorizationUrlDTO;
use KevinRider\LaravelEtrade\Dtos\CancelOrderResponseDTO;
use KevinRider\LaravelEtrade\Dtos\GetQuotesResponseDTO;
use KevinRider\LaravelEtrade\Dtos\DeleteAlertsResponseDTO;
use KevinRider\LaravelEtrade\Dtos\EtradeAccessTokenDTO;
use KevinRider\LaravelEtrade\Dtos\ListTransactionDetailsResponseDTO;
use KevinRider\LaravelEtrade\Dtos\ListTransactionsResponseDTO;
use KevinRider\LaravelEtrade\Dtos\LookupResponseDTO;
use KevinRider\LaravelEtrade\Dtos\OrdersResponseDTO;
use KevinRider\LaravelEtrade\Dtos\OptionChainResponseDTO;
use KevinRider\LaravelEtrade\Dtos\OptionExpireDateResponseDTO;
use KevinRider\LaravelEtrade\Dtos\PlaceOrderResponseDTO;
use KevinRider\LaravelEtrade\Dtos\PreviewOrderResponseDTO;
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
use KevinRider\LaravelEtrade\Dtos\ViewPortfolioResponseDTO;
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
        if ($response->getStatusCode() !== 200 || $response->getBody()->getContents() != EtradeConfig::OAUTH_REVOKE_ACCESS_TOKEN_SUCCESS) {
            throw new EtradeApiException('Failed to revoke access token');
        }
    }

    /**
     * @return AccountListResponseDTO
     * @throws EtradeApiException
     * @throws GuzzleException
     */
    public function getAccountList(): AccountListResponseDTO
    {
        $accessTokenDTO = $this->getAccessToken();

        $this->client = $this->createOauthClient([
            'token' => $accessTokenDTO->oauthToken,
            'token_secret' => $accessTokenDTO->oauthTokenSecret,
        ]);

        $response = $this->client->get(EtradeConfig::ACCOUNTS_LIST);

        if ($response->getStatusCode() !== 200) {
            throw new EtradeApiException('Failed to get account list');
        }

        return AccountListResponseDTO::fromXml($response->getBody()->getContents());
    }

    /**
     * @param AccountBalanceRequestDTO $accountBalanceRequestDTO
     * @return AccountBalanceResponseDTO
     * @throws EtradeApiException
     * @throws GuzzleException
     */
    public function getAccountBalance(AccountBalanceRequestDTO $accountBalanceRequestDTO): AccountBalanceResponseDTO
    {
        $accessTokenDTO = $this->getAccessToken();

        $this->client = $this->createOauthClient([
            'token' => $accessTokenDTO->oauthToken,
            'token_secret' => $accessTokenDTO->oauthTokenSecret,
        ]);

        if (!isset($accountBalanceRequestDTO->accountIdKey)) {
            throw new EtradeApiException('accountIdKey is required!');
        }

        $uri = str_replace('{accountIdKey}', $accountBalanceRequestDTO->accountIdKey, EtradeConfig::ACCOUNTS_BALANCE);

        $queryParams = [];
        foreach (AccountBalanceRequestDTO::ALLOWED_QUERY_PARAMS as $param) {
            if (isset($accountBalanceRequestDTO->$param)) {
                $queryParams[$param] = $accountBalanceRequestDTO->$param;
            }
        }
        $response = $this->client->get($uri, ['query' => $queryParams]);

        if ($response->getStatusCode() !== 200) {
            throw new EtradeApiException('Failed to get account balance');
        }
        return AccountBalanceResponseDTO::fromXml($response->getBody()->getContents());
    }

    /**
     * @param ListTransactionsRequestDTO $listTransactionsRequestDTO
     * @return ListTransactionsResponseDTO
     * @throws EtradeApiException
     * @throws GuzzleException
     */
    public function getAccountTransactions(ListTransactionsRequestDTO $listTransactionsRequestDTO): ListTransactionsResponseDTO
    {
        $accessTokenDTO = $this->getAccessToken();

        $this->client = $this->createOauthClient([
            'token' => $accessTokenDTO->oauthToken,
            'token_secret' => $accessTokenDTO->oauthTokenSecret,
        ]);

        $uri = str_replace('{accountIdKey}', $listTransactionsRequestDTO->accountIdKey, EtradeConfig::ACCOUNTS_TRANSACTIONS);

        $queryParams = [];
        foreach (ListTransactionsRequestDTO::ALLOWED_QUERY_PARAMS as $param) {
            if (isset($listTransactionsRequestDTO->$param)) {
                $queryParams[$param] = $listTransactionsRequestDTO->$param;
            }
        }
        $response = $this->client->get($uri, ['query' => $queryParams]);

        if ($response->getStatusCode() !== 200) {
            throw new EtradeApiException('Failed to get account transactions');
        }

        return ListTransactionsResponseDTO::fromXml($response->getBody()->getContents());
    }

    /**
     * @param ListTransactionDetailsRequestDTO $listTransactionDetailsRequestDTO
     * @return ListTransactionDetailsResponseDTO
     * @throws EtradeApiException
     * @throws GuzzleException
     */
    public function getAccountTransactionDetails(ListTransactionDetailsRequestDTO $listTransactionDetailsRequestDTO): ListTransactionDetailsResponseDTO
    {
        $accessTokenDTO = $this->getAccessToken();

        $this->client = $this->createOauthClient([
            'token' => $accessTokenDTO->oauthToken,
            'token_secret' => $accessTokenDTO->oauthTokenSecret,
        ]);

        $uri = str_replace(['{accountIdKey}', '{transactionId}'], [$listTransactionDetailsRequestDTO->accountIdKey, $listTransactionDetailsRequestDTO->transactionId], EtradeConfig::ACCOUNTS_TRANSACTIONS_DETAILS);

        $queryParams = [];
        foreach (ListTransactionDetailsRequestDTO::ALLOWED_QUERY_PARAMS as $param) {
            if (isset($listTransactionDetailsRequestDTO->$param)) {
                $queryParams[$param] = $listTransactionDetailsRequestDTO->$param;
            }
        }
        $response = $this->client->get($uri, ['query' => $queryParams]);

        if ($response->getStatusCode() !== 200) {
            throw new EtradeApiException('Failed to get account transaction details');
        }

        return ListTransactionDetailsResponseDTO::fromXml($response->getBody()->getContents());
    }

    /**
     * @param ViewPortfolioRequestDTO $viewPortfolioRequestDTO
     * @return ViewPortfolioResponseDTO
     * @throws EtradeApiException
     * @throws GuzzleException
     */
    public function getViewPortfolio(ViewPortfolioRequestDTO $viewPortfolioRequestDTO): ViewPortfolioResponseDTO
    {
        $accessTokenDTO = $this->getAccessToken();

        $this->client = $this->createOauthClient([
            'token' => $accessTokenDTO->oauthToken,
            'token_secret' => $accessTokenDTO->oauthTokenSecret,
        ]);

        if (!isset($viewPortfolioRequestDTO->accountIdKey)) {
            throw new EtradeApiException('accountIdKey is required!');
        }

        $uri = str_replace('{accountIdKey}', $viewPortfolioRequestDTO->accountIdKey, EtradeConfig::ACCOUNTS_PORTFOLIO);

        $queryParams = [];
        foreach (ViewPortfolioRequestDTO::ALLOWED_QUERY_PARAMS as $param) {
            if (isset($viewPortfolioRequestDTO->$param)) {
                $queryParams[$param] = $viewPortfolioRequestDTO->$param;
            }
        }

        $response = $this->client->get($uri, ['query' => $queryParams]);

        if ($response->getStatusCode() !== 200) {
            throw new EtradeApiException('Failed to view account portfolio');
        }
        return ViewPortfolioResponseDTO::fromXml($response->getBody()->getContents());
    }

    /**
     * @param ListAlertsRequestDTO $listAlertsRequestDTO
     * @return ListAlertsResponseDTO
     * @throws EtradeApiException
     * @throws GuzzleException
     */
    public function getAlerts(ListAlertsRequestDTO $listAlertsRequestDTO): ListAlertsResponseDTO
    {
        $accessTokenDTO = $this->getAccessToken();

        $this->client = $this->createOauthClient([
            'token' => $accessTokenDTO->oauthToken,
            'token_secret' => $accessTokenDTO->oauthTokenSecret,
        ]);

        $queryParams = [];
        foreach (ListAlertsRequestDTO::ALLOWED_QUERY_PARAMS as $param) {
            if (isset($listAlertsRequestDTO->$param)) {
                $queryParams[$param] = $this->normalizeQueryParamValue($listAlertsRequestDTO->$param);
            }
        }

        $response = $this->client->get(EtradeConfig::ALERTS_LIST, ['query' => $queryParams]);

        if ($response->getStatusCode() !== 200) {
            throw new EtradeApiException('Failed to list alerts');
        }

        return ListAlertsResponseDTO::fromXml($response->getBody()->getContents());
    }

    /**
     * @param ListAlertDetailsRequestDTO $listAlertDetailsRequestDTO
     * @return ListAlertDetailsResponseDTO
     * @throws EtradeApiException
     * @throws GuzzleException
     */
    public function getAlertDetails(ListAlertDetailsRequestDTO $listAlertDetailsRequestDTO): ListAlertDetailsResponseDTO
    {
        if (!isset($listAlertDetailsRequestDTO->alertId)) {
            throw new EtradeApiException('alertId is required!');
        }

        $accessTokenDTO = $this->getAccessToken();

        $this->client = $this->createOauthClient([
            'token' => $accessTokenDTO->oauthToken,
            'token_secret' => $accessTokenDTO->oauthTokenSecret,
        ]);

        $uri = str_replace('{alertId}', $listAlertDetailsRequestDTO->alertId, EtradeConfig::ALERTS_DETAILS);

        $queryParams = [];
        foreach (ListAlertDetailsRequestDTO::ALLOWED_QUERY_PARAMS as $param) {
            if (isset($listAlertDetailsRequestDTO->$param)) {
                $queryParams[$param] = $this->normalizeQueryParamValue($listAlertDetailsRequestDTO->$param);
            }
        }

        $response = $this->client->get($uri, ['query' => $queryParams]);

        if ($response->getStatusCode() !== 200) {
            throw new EtradeApiException('Failed to get alert details');
        }

        return ListAlertDetailsResponseDTO::fromXml($response->getBody()->getContents());
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

        $accessTokenDTO = $this->getAccessToken();

        $this->client = $this->createOauthClient([
            'token' => $accessTokenDTO->oauthToken,
            'token_secret' => $accessTokenDTO->oauthTokenSecret,
        ]);

        $uri = str_replace('{alertId}', $alertIdPathSegment, EtradeConfig::ALERTS_DELETE);

        $response = $this->client->delete($uri);

        if ($response->getStatusCode() !== 200) {
            throw new EtradeApiException('Failed to delete alerts');
        }

        return DeleteAlertsResponseDTO::fromXml($response->getBody()->getContents());
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

        $accessTokenDTO = $this->getAccessToken();

        $this->client = $this->createOauthClient([
            'token' => $accessTokenDTO->oauthToken,
            'token_secret' => $accessTokenDTO->oauthTokenSecret,
        ]);

        $uri = str_replace('{accountIdKey}', $listOrdersRequestDTO->accountIdKey, EtradeConfig::ORDER_LIST);

        $queryParams = [];
        foreach (ListOrdersRequestDTO::ALLOWED_QUERY_PARAMS as $param) {
            if (isset($listOrdersRequestDTO->$param)) {
                $queryParams[$param] = $this->normalizeQueryParamValue($listOrdersRequestDTO->$param);
            }
        }

        $response = $this->client->get($uri, ['query' => $queryParams]);

        if ($response->getStatusCode() !== 200) {
            throw new EtradeApiException('Failed to list orders');
        }

        return OrdersResponseDTO::fromXml($response->getBody()->getContents());
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

        $accessTokenDTO = $this->getAccessToken();

        $this->client = $this->createOauthClient([
            'token' => $accessTokenDTO->oauthToken,
            'token_secret' => $accessTokenDTO->oauthTokenSecret,
        ]);

        $uri = str_replace('{accountIdKey}', $previewOrderRequestDTO->accountIdKey, EtradeConfig::ORDER_PREVIEW);

        $response = $this->client->post($uri, ['json' => $previewOrderRequestDTO->toRequestBody()]);

        if ($response->getStatusCode() !== 200) {
            throw new EtradeApiException('Failed to preview order');
        }

        return PreviewOrderResponseDTO::fromJson($response->getBody()->getContents());
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

        $accessTokenDTO = $this->getAccessToken();

        $this->client = $this->createOauthClient([
            'token' => $accessTokenDTO->oauthToken,
            'token_secret' => $accessTokenDTO->oauthTokenSecret,
        ]);

        $uri = str_replace('{accountIdKey}', $placeOrderRequestDTO->accountIdKey, EtradeConfig::ORDER_PLACE);

        $response = $this->client->post($uri, ['json' => $placeOrderRequestDTO->toRequestBody()]);

        if ($response->getStatusCode() !== 200) {
            throw new EtradeApiException('Failed to place order');
        }

        return PlaceOrderResponseDTO::fromJson($response->getBody()->getContents());
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

        $accessTokenDTO = $this->getAccessToken();

        $this->client = $this->createOauthClient([
            'token' => $accessTokenDTO->oauthToken,
            'token_secret' => $accessTokenDTO->oauthTokenSecret,
        ]);

        $uri = str_replace('{accountIdKey}', $cancelOrderRequestDTO->accountIdKey, EtradeConfig::ORDER_CANCEL);

        $response = $this->client->put($uri, ['json' => $cancelOrderRequestDTO->toRequestBody()]);

        if ($response->getStatusCode() !== 200) {
            throw new EtradeApiException('Failed to cancel order');
        }

        return CancelOrderResponseDTO::fromJson($response->getBody()->getContents());
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

        $accessTokenDTO = $this->getAccessToken();

        $this->client = $this->createOauthClient([
            'token' => $accessTokenDTO->oauthToken,
            'token_secret' => $accessTokenDTO->oauthTokenSecret,
        ]);

        $uri = str_replace('{search}', $lookupRequestDTO->search, EtradeConfig::MARKET_LOOKUP);

        $response = $this->client->get($uri);

        if ($response->getStatusCode() !== 200) {
            throw new EtradeApiException('Failed to lookup product');
        }

        return LookupResponseDTO::fromXml($response->getBody()->getContents());
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

        $accessTokenDTO = $this->getAccessToken();

        $this->client = $this->createOauthClient([
            'token' => $accessTokenDTO->oauthToken,
            'token_secret' => $accessTokenDTO->oauthTokenSecret,
        ]);

        $queryParams = [];
        foreach (GetOptionChainsRequestDTO::ALLOWED_QUERY_PARAMS as $param) {
            if (isset($getOptionChainsRequestDTO->$param)) {
                $queryParams[$param] = $this->normalizeQueryParamValue($getOptionChainsRequestDTO->$param);
            }
        }

        $response = $this->client->get(EtradeConfig::MARKET_OPTION_CHAINS, ['query' => $queryParams]);

        if ($response->getStatusCode() !== 200) {
            throw new EtradeApiException('Failed to get option chains');
        }

        return OptionChainResponseDTO::fromXml($response->getBody()->getContents());
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

        $accessTokenDTO = $this->getAccessToken();

        $this->client = $this->createOauthClient([
            'token' => $accessTokenDTO->oauthToken,
            'token_secret' => $accessTokenDTO->oauthTokenSecret,
        ]);

        $queryParams = [];
        foreach (GetOptionExpireDatesRequestDTO::ALLOWED_QUERY_PARAMS as $param) {
            if (isset($getOptionExpireDatesRequestDTO->$param)) {
                $queryParams[$param] = $this->normalizeQueryParamValue($getOptionExpireDatesRequestDTO->$param);
            }
        }

        $response = $this->client->get(EtradeConfig::MARKET_OPTION_EXPIRY, ['query' => $queryParams]);

        if ($response->getStatusCode() !== 200) {
            throw new EtradeApiException('Failed to get option expiration dates');
        }

        return OptionExpireDateResponseDTO::fromXml($response->getBody()->getContents());
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

        $accessTokenDTO = $this->getAccessToken();

        $this->client = $this->createOauthClient([
            'token' => $accessTokenDTO->oauthToken,
            'token_secret' => $accessTokenDTO->oauthTokenSecret,
        ]);

        $uri = str_replace('{symbols}', $getQuotesRequestDTO->getSymbols(), EtradeConfig::MARKET_QUOTES);

        $queryParams = [];
        foreach (GetQuotesRequestDTO::ALLOWED_QUERY_PARAMS as $param) {
            if (isset($getQuotesRequestDTO->$param)) {
                $queryParams[$param] = $this->normalizeQueryParamValue($getQuotesRequestDTO->$param);
            }
        }

        $response = $this->client->get($uri, ['query' => $queryParams]);

        if ($response->getStatusCode() !== 200) {
            throw new EtradeApiException('Failed to get quotes');
        }

        return GetQuotesResponseDTO::fromXml($response->getBody()->getContents());
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
        return json_decode(Crypt::decryptString($encryptedTokenArray), true);
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
        $accessTokenDTO = $this->getAccessToken();

        $this->client = $this->createOauthClient([
            'token' => $accessTokenDTO->oauthToken,
            'token_secret' => $accessTokenDTO->oauthTokenSecret,
        ]);

        $uri = str_replace(
            ['{accountIdKey}', '{orderId}'],
            [$dto->accountIdKey, $dto->orderId],
            $uriTemplate
        );

        $response = $this->client->put($uri, ['json' => $payload]);

        if ($response->getStatusCode() !== 200) {
            throw new EtradeApiException($errorMessage);
        }

        return $responseParser($response->getBody()->getContents());
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
}
