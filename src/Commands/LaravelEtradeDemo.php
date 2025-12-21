<?php

namespace KevinRider\LaravelEtrade\Commands;

use Carbon\CarbonInterface;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use KevinRider\LaravelEtrade\Dtos\Response\AccountBalanceResponseDTO;
use KevinRider\LaravelEtrade\Dtos\Response\CancelOrderResponseDTO;
use KevinRider\LaravelEtrade\Dtos\Response\DeleteAlertsResponseDTO;
use KevinRider\LaravelEtrade\Dtos\Response\GetQuotesResponseDTO;
use KevinRider\LaravelEtrade\Dtos\Response\ListAlertDetailsResponseDTO;
use KevinRider\LaravelEtrade\Dtos\Response\ListAlertsResponseDTO;
use KevinRider\LaravelEtrade\Dtos\Response\ListTransactionDetailsResponseDTO;
use KevinRider\LaravelEtrade\Dtos\Response\ListTransactionsResponseDTO;
use KevinRider\LaravelEtrade\Dtos\Response\LookupResponseDTO;
use KevinRider\LaravelEtrade\Dtos\Response\OptionChainResponseDTO;
use KevinRider\LaravelEtrade\Dtos\Response\OptionExpireDateResponseDTO;
use KevinRider\LaravelEtrade\Dtos\Response\OrdersResponseDTO;
use KevinRider\LaravelEtrade\Dtos\Response\PlaceOrderResponseDTO;
use KevinRider\LaravelEtrade\Dtos\Response\PreviewOrderResponseDTO;
use KevinRider\LaravelEtrade\Dtos\Request\AccountBalanceRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\CancelOrderRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\DeleteAlertsRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\GetOptionChainsRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\GetOptionExpireDatesRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\GetQuotesRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\ListAlertDetailsRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\ListAlertsRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\ListOrdersRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\ListTransactionDetailsRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\ListTransactionsRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\LookupRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\ViewPortfolioRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Response\ViewPortfolioResponseDTO;
use KevinRider\LaravelEtrade\EtradeApiClient;
use KevinRider\LaravelEtrade\EtradeOrderBuilder;
use KevinRider\LaravelEtrade\Exceptions\EtradeApiException;
use Random\RandomException;
use ReflectionException;
use Throwable;

/**
 * Interactive console demo for the Laravel E*TRADE client.
 *
 * Walks through authentication, read-only endpoints (accounts, market data, alerts, order list),
 * order lifecycle previews/placements, and destructive flows (delete alerts, cancel order, revoke token).
 * WARNING: The orders menu option uses your configured credentials and will place/modify real live orders
 * unless the underlying account itself is a sandbox (laravel-etrade.production === false). Proceed with
 * extreme caution when confirming place/change/cancel prompts.
 */
class LaravelEtradeDemo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel-etrade:demo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run a basic demo showcasing Laravel E*TRADE integration.';

    public function __construct(private readonly EtradeApiClient $apiClient)
    {
        parent::__construct();
    }

    /**
     * @return int
     * @throws RandomException
     * @throws ReflectionException
     */
    public function handle(): int
    {
        $this->intro();

        while (true) {
            $this->printModeBanner();
            $choice = $this->choice(
                'What would you like to explore?',
                [
                    'auth' => 'Authenticate (full OAuth walk-through)',
                    'reads' => 'Read-only demos (accounts, market, alerts, orders list)',
                    'orders' => 'Order lifecycle demos (preview/place/change with double-confirm)',
                    'destructive' => 'Destructive ops (delete alert, cancel order, revoke token)',
                    'exit' => 'Exit',
                ],
                'auth'
            );

            if ($choice === 'exit') {
                break;
            }

            if (in_array($choice, ['reads', 'orders', 'destructive'], true) && !$this->hasActiveAuthToken()) {
                continue;
            }

            match ($choice) {
                'auth' => $this->authenticateFlow(),
                'reads' => $this->runReadOnlyMenu(),
                'orders' => $this->runOrderMenu(),
                'destructive' => $this->runDestructiveMenu(),
                default => null,
            };
        }

        return self::SUCCESS;
    }

    /**
     * @return void
     */
    private function intro(): void
    {
        $this->line('');
        $this->line('👋 Welcome to the Laravel E*TRADE interactive demo. 👋');
        $this->line('');
    }

    /**
     * @return void
     */
    private function authenticateFlow(): void
    {
        $this->section('OAuth: request token -> verifier -> access token');

        if (!$this->confirm('Ready to start the OAuth flow now?')) {
            $this->comment('Skipping authentication. You can come back later via the menu.');
            return;
        }

        try {
            $authUrl = $this->apiClient->getAuthorizationUrl();
        } catch (Throwable $e) {
            $this->reportError('Unable to get request token', $e);
            return;
        }

        $this->line('1) Open this URL in your browser and log in:');
        $this->line($authUrl->authorizationUrl);
        $this->line('2) Approve the app and copy the verifier code displayed by E*TRADE.');

        $verifier = $this->ask('Paste the verifier code here');
        if (empty($verifier)) {
            $this->warn('No verifier provided. Aborting authentication.');
            return;
        }

        try {
            $this->apiClient->requestAccessTokenAndStore(trim($verifier));
            $this->info('Access token stored in the configured Laravel cache store. You are authenticated.');
        } catch (Throwable $e) {
            $this->reportError('Failed to exchange verifier for access token', $e);
        }
    }

    /**
     * @return void
     */
    private function runReadOnlyMenu(): void
    {
        $this->section('Read-only demos');
        $this->printModeBanner();

        $choices = [
            'full' => 'Run the full read-only tour (recommended)',
            'accounts' => 'Account endpoints (list, balance, portfolio, transactions)',
            'market' => 'Market data (lookup, quotes, option expirations + chains)',
            'alerts' => 'Alerts (list + details)',
            'orders-list' => 'Orders list (paged or all)',
            'back' => 'Back',
        ];

        $selection = $this->choice('Pick a read-only demo', $choices, 'full');

        if ($selection === 'back') {
            return;
        }

        match ($selection) {
            'full' => $this->runReadOnlyTour(),
            'accounts' => $this->runAccountDemos(),
            'market' => $this->runMarketDemos(),
            'alerts' => $this->runAlertDemos(),
            'orders-list' => $this->runOrderListDemo(),
            default => null,
        };
    }

    /**
     * @return void
     */
    private function runReadOnlyTour(): void
    {
        $accountIdKey = $this->resolveAccountIdKey();
        if (!$accountIdKey) {
            return;
        }

        $this->runAccountDemos($accountIdKey);
        $this->runMarketDemos();
        $this->runAlertDemos();
        $this->runOrderListDemo($accountIdKey);
    }

    /**
     * @param string|null $accountIdKey
     * @return void
     */
    private function runAccountDemos(?string $accountIdKey = null): void
    {
        $this->subSection('Accounts');

        $accounts = $this->withApiCall('Account list', fn () => $this->apiClient->getAccountList());
        $this->renderAccounts($accounts);

        $accountIdKey ??= $this->pickAccountFromList($accounts);
        if (!$accountIdKey) {
            $this->warn('No account selected; skipping account-specific calls.');
            return;
        }

        $balanceDto = $this->withApiCall('Account balance', function () use ($accountIdKey) {
            return $this->apiClient->getAccountBalance(new AccountBalanceRequestDTO([
                'accountIdKey' => $accountIdKey,
                'realTimeNAV' => 'true',
            ]));
        });
        $this->renderResponse($balanceDto);

        $portfolio = $this->withApiCall('Portfolio (holdings)', function () use ($accountIdKey) {
            return $this->apiClient->getViewPortfolio(new ViewPortfolioRequestDTO([
                'accountIdKey' => $accountIdKey,
                'view' => 'PERFORMANCE',
                'totalsRequired' => true,
            ]));
        });
        $this->renderResponse($portfolio);

        $transactions = $this->withApiCall('Recent transactions', function () use ($accountIdKey) {
            return $this->apiClient->getAccountTransactions(new ListTransactionsRequestDTO([
                'accountIdKey' => $accountIdKey,
                'count' => 10,
                'sortOrder' => 'DESC',
            ]));
        });
        $this->renderResponse($transactions);

        if ($transactions && !empty($transactions->transactions)) {
            $firstId = $transactions->transactions[0]->transactionId ?? null;
            if ($firstId && $this->confirm("Fetch details for transaction {$firstId}?")) {
                $details = $this->withApiCall('Transaction details', function () use ($accountIdKey, $firstId) {
                    return $this->apiClient->getAccountTransactionDetails(new ListTransactionDetailsRequestDTO([
                        'accountIdKey' => $accountIdKey,
                        'transactionId' => $firstId,
                    ]));
                });
                $this->renderResponse($details);
            }
        }
    }

    /**
     * @return void
     */
    private function runMarketDemos(): void
    {
        $this->subSection('Market data');

        $search = $this->ask('Symbol search term for lookup', 'SPY');
        $lookup = $this->withApiCall('Product lookup', function () use ($search) {
            return $this->apiClient->lookupProduct(new LookupRequestDTO(['search' => $search]));
        });
        $this->renderResponse($lookup);

        $symbolsRaw = $this->ask('Comma-separated symbols for quotes', 'SPY,MSFT,QQQ');
        $symbols = array_values(array_filter(array_map('trim', explode(',', (string) $symbolsRaw))));

        if (!empty($symbols)) {
            $quotes = $this->withApiCall('Get quotes', function () use ($symbols) {
                return $this->apiClient->getQuotes(new GetQuotesRequestDTO([
                    'symbols' => $symbols,
                    'detailFlag' => 'ALL',
                    'requireEarningsDate' => false,
                ]));
            });
            $this->renderResponse($quotes);
        }

        $optionSymbol = $this->ask('Symbol for option expirations & chains', 'SPY');
        $expiryType = $this->choice('Expiry type', ['ALL', 'WEEKLY', 'MONTHLY'], 'ALL');
        $defaultExpiry = $this->defaultOptionExpiryDate();

        $expirations = $this->withApiCall('Option expiration dates', function () use ($optionSymbol, $expiryType) {
            return $this->apiClient->getOptionExpireDates(new GetOptionExpireDatesRequestDTO([
                'symbol' => $optionSymbol,
                'expiryType' => $expiryType,
            ]));
        });
        $this->renderResponse($expirations);

        $year = (int) $this->ask('Expiry year for option chain', (string) $defaultExpiry->year);
        $month = (int) $this->ask('Expiry month for option chain (1-12)', (string) $defaultExpiry->month);
        $day = (int) $this->ask('Expiry day for option chain', (string) $defaultExpiry->day);
        $strikes = (int) $this->ask('How many strikes around the money?', '5');

        $chains = $this->withApiCall('Option chain', function () use ($optionSymbol, $year, $month, $day, $strikes) {
            return $this->apiClient->getOptionChains(new GetOptionChainsRequestDTO([
                'symbol' => $optionSymbol,
                'expiryYear' => $year,
                'expiryMonth' => $month,
                'expiryDay' => $day,
                'noOfStrikes' => $strikes,
                'includeWeekly' => true,
                'priceType' => 'ATNM',
            ]));
        });
        $this->renderResponse($chains);
    }

    /**
     * @return void
     */
    private function runAlertDemos(): void
    {
        $this->subSection('Alerts');

        $alerts = $this->withApiCall('List alerts', function () {
            return $this->apiClient->getAlerts(new ListAlertsRequestDTO([
                'count' => 10,
                'direction' => 'DESC',
            ]));
        });
        $this->renderResponse($alerts);

        if ($alerts && !empty($alerts->alerts)) {
            $firstAlertId = $alerts->alerts[0]->alertId ?? null;
            $selected = $this->ask('Alert ID to view details (blank to skip)', $firstAlertId);
            if (!empty($selected)) {
                $details = $this->withApiCall('Alert details', function () use ($selected) {
                    return $this->apiClient->getAlertDetails(new ListAlertDetailsRequestDTO([
                        'alertId' => (int) $selected,
                        'htmlTags' => false,
                    ]));
                });
                $this->renderResponse($details);
            }
        }
    }

    /**
     * @param string|null $accountIdKey
     * @return void
     */
    private function runOrderListDemo(?string $accountIdKey = null): void
    {
        $this->subSection('Orders list');
        $accountIdKey ??= $this->resolveAccountIdKey();
        if (!$accountIdKey) {
            return;
        }

        $status = $this->choice('Status filter', ['OPEN', 'CANCELLED', 'EXECUTED', 'ALL'], 'ALL');
        $count = (int) $this->ask('How many orders to fetch (per page)', '10');

        $listRequest = new ListOrdersRequestDTO([
            'accountIdKey' => $accountIdKey,
            'status' => $status === 'ALL' ? null : $status,
            'count' => $count,
            'callDepth' => 3,
        ]);

        $orders = $this->withApiCall('Orders', fn () => $this->apiClient->listAllOrders($listRequest));
        $this->renderResponse($orders);
    }

    /**
     * @return void
     * @throws RandomException
     * @throws ReflectionException
     */
    private function runOrderMenu(): void
    {
        $this->section('Order lifecycle demos.');
        $this->printModeBanner();

        $accountIdKey = $this->resolveAccountIdKey();
        if (!$accountIdKey) {
            return;
        }

        $scenario = $this->choice(
            'Pick an order scenario to preview (all limit-only 🚨🚨🚨 BE CAREFUL! 🚨🚨🚨)',
            [
                'equity' => 'Equity limit buy',
                'single-option' => 'Single-leg option (long call)',
                'vertical' => 'Two-leg option vertical',
                'three-leg' => 'Three-leg (call spread + short put)',
                'iron-condor' => 'Four-leg iron condor',
                'buy-write' => 'Buy-write (stock + covered call)',
                'collar' => 'Collar (stock + long put + short call)',
                'back' => 'Back',
            ],
            'equity'
        );

        if ($scenario === 'back') {
            return;
        }

        $builder = $this->buildOrderByScenario($scenario, $accountIdKey);
        if (!$builder) {
            return;
        }

        $this->runOrderLifecycle($builder);
    }

    /**
     * @param EtradeOrderBuilder $builder
     * @return void
     * @throws RandomException
     * @throws ReflectionException
     */
    private function runOrderLifecycle(EtradeOrderBuilder $builder): void
    {
        $previewResponse = $this->withApiCall('Previewing order (dry-run)', fn () => $this->apiClient->previewOrder($builder->buildPreviewRequest()));
        if (!$previewResponse) {
            return;
        }

        $this->renderResponse($previewResponse);

        $previewIds = collect($previewResponse->previewIds ?? [])->map(fn ($dto) => $dto->previewId ?? null)->filter()->all();
        if (empty($previewIds)) {
            $this->warn('Preview returned no previewIds; cannot place this order.');
            return;
        }

        if (!$this->confirmDanger('Place this order live? (double confirmation enforced)')) {
            $this->comment('Skipping live placement. Preview complete.');
            return;
        }

        $placeResponse = $this->withApiCall('Placing live order', fn () => $this->apiClient->placeOrder($builder->buildPlaceRequest($previewIds)));
        if (!$placeResponse) {
            return;
        }

        $this->renderResponse($placeResponse);

        $placedOrderId = $placeResponse->orderId ?? ($placeResponse->orderIds[0]->orderId ?? null);
        if (!$placedOrderId) {
            $this->warn('No orderId returned; cannot demonstrate change/cancel.');
            return;
        }

        if ($this->confirm('Preview a change on this order?')) {
            $newLimit = (float) $this->ask('New limit price for change preview', (string) ($this->extractLimitPrice($builder) * 0.5));

            $builder = $this->cloneBuilderWithNewLimit($builder, $newLimit)->orderId($placedOrderId);
            $builder->clientOrderId($this->randomOrderId());

            $changePreview = $this->withApiCall('Preview change order', fn () => $this->apiClient->previewChangeOrder($builder->buildPreviewRequest()));
            $this->renderResponse($changePreview);

            $changePreviewIds = collect($changePreview?->previewIds ?? [])->map(fn ($dto) => $dto->previewId ?? null)->filter()->all();
            if (!empty($changePreviewIds) && $this->confirmDanger('Place the change order live?')) {
                $changePlaced = $this->withApiCall('Placing change order', fn () => $this->apiClient->placeChangeOrder($builder->buildPlaceRequest($changePreviewIds)));
                $placedOrderId = $changePlaced->orderId ?? ($changePlaced->orderIds[0]->orderId ?? null);
                $this->renderResponse($changePlaced);
            }
        }

        if ($this->confirmDanger('Cancel this order now? (destructive)')) {
            $cancelled = $this->withApiCall('Cancel order', function () use ($builder, $placedOrderId) {
                return $this->apiClient->cancelOrder(new CancelOrderRequestDTO([
                    'accountIdKey' => $this->extractAccountId($builder),
                    'orderId' => $placedOrderId,
                ]));
            });
            $this->renderResponse($cancelled);
        }
    }

    /**
     * @return void
     */
    private function runDestructiveMenu(): void
    {
        $this->section('Destructive operations (extra prompts)');
        $this->printModeBanner();

        $choice = $this->choice(
            'Pick an action',
            [
                'delete-alerts' => 'Delete alerts by id',
                'cancel-order' => 'Cancel an order by id',
                'revoke' => 'Revoke access token',
                'back' => 'Back',
            ],
            'delete-alerts'
        );

        if ($choice === 'back') {
            return;
        }

        match ($choice) {
            'delete-alerts' => $this->deleteAlertsFlow(),
            'cancel-order' => $this->cancelOrderFlow(),
            'revoke' => $this->revokeTokenFlow(),
            default => null,
        };
    }

    /**
     * @return void
     */
    private function deleteAlertsFlow(): void
    {
        $alerts = $this->withApiCall('List alerts first', fn () => $this->apiClient->getAlerts(new ListAlertsRequestDTO(['count' => 10])));
        $this->renderResponse($alerts);

        $idsRaw = $this->ask('Comma-separated alert IDs to delete (blank to abort)');
        $ids = array_values(array_filter(array_map('trim', explode(',', (string) $idsRaw))));

        if (empty($ids)) {
            $this->warn('No alert IDs provided. Skipping delete.');
            return;
        }

        if (!$this->confirmDanger('Delete these alerts now?')) {
            $this->comment('Skipping alert deletion.');
            return;
        }

        $deleted = $this->withApiCall('Deleting alerts', fn () => $this->apiClient->deleteAlerts(new DeleteAlertsRequestDTO(['alertIds' => $ids])));
        $this->renderResponse($deleted);
    }

    /**
     * @return void
     */
    private function cancelOrderFlow(): void
    {
        $accountIdKey = $this->resolveAccountIdKey();
        if (!$accountIdKey) {
            return;
        }

        $orderId = (int) $this->ask('Order ID to cancel (from a live order)', '');
        if (!$orderId) {
            $this->warn('No order ID provided. Skipping cancel.');
            return;
        }

        if (!$this->confirmDanger("Cancel order {$orderId}?")) {
            $this->comment('Skipping cancel.');
            return;
        }

        $cancelled = $this->withApiCall('Cancelling order', fn () => $this->apiClient->cancelOrder(new CancelOrderRequestDTO([
            'accountIdKey' => $accountIdKey,
            'orderId' => $orderId,
        ])));
        $this->renderResponse($cancelled);
    }

    /**
     * @return void
     */
    private function revokeTokenFlow(): void
    {
        if (!$this->confirmDanger('Revoke the cached access token? This will require re-authentication.')) {
            $this->comment('Skipping token revoke.');
            return;
        }

        try {
            $this->apiClient->revokeAccessToken();
            $this->info('Access token revoked successfully.');
        } catch (Throwable $e) {
            $this->reportError('Revoking access token', $e);
        }
    }

    /**
     * @param mixed $accounts
     * @return void
     */
    private function renderAccounts(mixed $accounts): void
    {
        if (!$accounts || empty($accounts->accounts ?? [])) {
            $this->warn('No accounts returned.');
            return;
        }

        $rows = [];
        foreach ($accounts->accounts as $account) {
            $rows[] = [
                $account->accountId ?? '',
                $account->accountIdKey ?? '',
                $account->accountName ?? '',
                $account->accountType ?? '',
            ];
        }

        $this->table(['Account ID', 'Account Key', 'Name', 'Type'], $rows);
    }

    /**
     * @param mixed $payload
     * @return void
     */
    private function renderResponse(mixed $payload): void
    {
        if ($payload === null) {
            return;
        }

        match (true) {
            $payload instanceof AccountBalanceResponseDTO => $this->renderAccountBalance($payload),
            $payload instanceof ViewPortfolioResponseDTO => $this->renderPortfolio($payload),
            $payload instanceof ListTransactionsResponseDTO => $this->renderTransactions($payload),
            $payload instanceof ListTransactionDetailsResponseDTO => $this->renderTransactionDetail($payload),
            $payload instanceof LookupResponseDTO => $this->renderLookup($payload),
            $payload instanceof GetQuotesResponseDTO => $this->renderQuotes($payload),
            $payload instanceof OptionExpireDateResponseDTO => $this->renderOptionExpirations($payload),
            $payload instanceof OptionChainResponseDTO => $this->renderOptionChains($payload),
            $payload instanceof ListAlertsResponseDTO => $this->renderAlerts($payload),
            $payload instanceof ListAlertDetailsResponseDTO => $this->renderAlertDetail($payload),
            $payload instanceof OrdersResponseDTO => $this->renderOrders($payload),
            $payload instanceof PreviewOrderResponseDTO => $this->renderOrderPreview($payload),
            $payload instanceof PlaceOrderResponseDTO => $this->renderOrderPlacement($payload),
            $payload instanceof CancelOrderResponseDTO => $this->renderCancelledOrder($payload),
            $payload instanceof DeleteAlertsResponseDTO => $this->renderDeleteAlerts($payload),
            default => $this->renderFallbackTable($payload),
        };
    }

    /**
     * @param AccountBalanceResponseDTO $balance
     * @return void
     */
    private function renderAccountBalance(AccountBalanceResponseDTO $balance): void
    {
        $this->table(
            ['Account', 'Type', 'As of', 'Mode', 'Option Lvl'],
            [[
                $balance->accountId ?? '-',
                $balance->accountType ?? '-',
                $balance->asOfDate ?? '-',
                strtoupper($balance->accountMode ?? ''),
                $balance->optionLevel ?? '-',
            ]]
        );

        $computed = $balance->computedBalance ?? null;
        $rows = [
            ['Cash available (invest)', $this->formatMoney($computed?->cashAvailableForInvestment)],
            ['Cash available (withdraw)', $this->formatMoney($computed?->cashAvailableForWithdrawal)],
            ['Net cash', $this->formatMoney($computed?->netCash)],
            ['Cash balance', $this->formatMoney($computed?->cashBalance)],
            ['Margin buying power', $this->formatMoney($computed?->marginBuyingPower)],
            ['Cash buying power', $this->formatMoney($computed?->cashBuyingPower)],
        ];

        $this->table(['Balance Metric', 'Amount'], $rows);
    }

    /**
     * @return void
     */
    private function printModeBanner(): void
    {
        $production = (bool) config('laravel-etrade.production');
        $message = $production
            ? 'WARNING: LIVE TRADING IS ENABLED. ORDERS WILL BE PLACED LIVE!'
            : 'Sandbox testing mode';

        $production ? $this->error($message) : $this->info($message);
    }

    /**
     * @param ViewPortfolioResponseDTO $portfolio
     * @return void
     */
    private function renderPortfolio(ViewPortfolioResponseDTO $portfolio): void
    {
        if (empty($portfolio->positions)) {
            $this->warn('No positions returned.');
            return;
        }

        $positions = array_slice($portfolio->positions, 0, 15);
        $rows = [];
        foreach ($positions as $position) {
            $rows[] = [
                $position->product?->symbol ?? '-',
                $this->formatNumber($position->quantity),
                $this->formatMoney($position->pricePaid),
                $this->formatPercent($position->daysGainPct),
                $this->formatMoney($position->marketValue),
                $this->formatPercent($position->totalGainPct),
            ];
        }

        $this->table(['Symbol', 'Qty', 'Last', 'Day %', 'Market Value', 'Total Gain %'], $rows);

        if (count($portfolio->positions) > count($positions)) {
            $this->comment('Showing first 15 positions.');
        }
    }

    /**
     * @param ListTransactionsResponseDTO $transactions
     * @return void
     */
    private function renderTransactions(ListTransactionsResponseDTO $transactions): void
    {
        if (empty($transactions->transactions ?? [])) {
            $this->warn('No transactions returned.');
            return;
        }

        $items = array_slice($transactions->transactions, 0, 15);
        $rows = [];
        foreach ($items as $transaction) {
            $rows[] = [
                $transaction->transactionId ?? '-',
                $this->truncate($transaction->description ?? '', 60),
                $this->formatMoney($transaction->amount ?? null),
                strtoupper((string) ($transaction->transactionType ?? '')),
                $this->formatTimestamp($transaction->transactionDate ?? null),
            ];
        }

        $this->table(['ID', 'Description', 'Amount', 'Type', 'Date'], $rows);

        if (count($transactions->transactions) > count($items)) {
            $this->comment('Showing first 15 transactions.');
        }
    }

    /**
     * @param ListTransactionDetailsResponseDTO $details
     * @return void
     */
    private function renderTransactionDetail(ListTransactionDetailsResponseDTO $details): void
    {
        $transaction = $details->transaction ?? null;
        if (!$transaction) {
            $this->warn('No transaction details returned.');
            return;
        }

        $brokerage = $transaction->brokerage ?? null;
        $rows = [
            ['Transaction ID', $transaction->transactionId ?? '-'],
            ['Type', strtoupper($transaction->transactionType ?? '')],
            ['Description', $transaction->description ?? '-'],
            ['Symbol', $brokerage?->product?->symbol ?? '-'],
            ['Action', strtoupper($brokerage?->transactionType ?? '')],
            ['Quantity', $this->formatNumber($brokerage?->quantity)],
            ['Price', $this->formatMoney($brokerage?->price)],
            ['Amount', $this->formatMoney($transaction->amount ?? null)],
            ['Date', $this->formatTimestamp($transaction->transactionDate ?? null)],
            ['Memo', $this->truncate($brokerage?->memo ?? '', 80)],
        ];

        $this->table(['Field', 'Value'], $rows);
    }

    /**
     * @param LookupResponseDTO $lookup
     * @return void
     */
    private function renderLookup(LookupResponseDTO $lookup): void
    {
        if (empty($lookup->data)) {
            $this->warn('No lookup results.');
            return;
        }

        $rows = [];
        foreach (array_slice($lookup->data, 0, 15) as $datum) {
            $rows[] = [
                $datum->symbol ?? '-',
                $datum->description ?? '-',
                strtoupper((string) ($datum->type ?? '')),
            ];
        }

        $this->table(['Symbol', 'Description', 'Type'], $rows);
    }

    /**
     * @param GetQuotesResponseDTO $quotes
     * @return void
     */
    private function renderQuotes(GetQuotesResponseDTO $quotes): void
    {
        if (empty($quotes->quoteData)) {
            $this->warn('No quotes returned.');
            return;
        }

        $rows = [];
        foreach (array_slice($quotes->quoteData, 0, 10) as $symbol => $quote) {
            $all = $quote->all;
            $rows[] = [
                is_string($symbol) ? $symbol : ($quote->product?->symbol ?? '-'),
                $this->formatMoney($all?->lastTrade),
                $this->formatPercent($all?->changeClosePercentage),
                $this->formatMoney($all?->bid),
                $this->formatMoney($all?->ask),
                $this->formatNumber($all?->totalVolume, 0),
            ];
        }

        $this->table(['Symbol', 'Last', 'Change %', 'Bid', 'Ask', 'Volume'], $rows);
    }

    /**
     * @param OptionExpireDateResponseDTO $expirations
     * @return void
     */
    private function renderOptionExpirations(OptionExpireDateResponseDTO $expirations): void
    {
        if (empty($expirations->expirationDates)) {
            $this->warn('No expiration dates returned.');
            return;
        }

        $rows = [];
        foreach (array_slice($expirations->expirationDates, 0, 15) as $expiration) {
            $rows[] = [
                $expiration->year ?? '-',
                $expiration->month ?? '-',
                $expiration->day ?? '-',
                strtoupper((string) ($expiration->expiryType ?? '')),
            ];
        }

        $this->table(['Year', 'Month', 'Day', 'Type'], $rows);
    }

    /**
     * @param OptionChainResponseDTO $chains
     * @return void
     */
    private function renderOptionChains(OptionChainResponseDTO $chains): void
    {
        if (empty($chains->optionPairs)) {
            $this->warn('No option chain data returned.');
            return;
        }

        if ($chains->nearPrice !== null) {
            $this->line('Near price: '.$this->formatMoney($chains->nearPrice));
        }

        $rows = [];
        foreach (array_slice($this->sortOptionPairsByStrike($chains->optionPairs), 0, 10) as $pair) {
            $call = $pair->call;
            $put = $pair->put;
            $rows[] = [
                $call?->strikePrice ?? $put?->strikePrice ?? '-',
                $this->formatBidAsk($call?->bid, $call?->ask),
                $this->formatMoney($call?->lastPrice),
                $this->formatBidAsk($put?->bid, $put?->ask),
                $this->formatMoney($put?->lastPrice),
                $this->formatVolumePair($call?->volume, $put?->volume),
            ];
        }

        $this->table(
            ['Strike', 'Call Bid/Ask', 'Call Last', 'Put Bid/Ask', 'Put Last', 'Volume (C/P)'],
            $rows
        );

        if (count($chains->optionPairs) > 10) {
            $this->comment('Showing first 10 strikes.');
        }
    }

    /**
     * @param ListAlertsResponseDTO $alerts
     * @return void
     */
    private function renderAlerts(ListAlertsResponseDTO $alerts): void
    {
        if (empty($alerts->alerts)) {
            $this->warn('No alerts returned.');
            return;
        }

        $rows = [];
        foreach ($alerts->alerts as $alert) {
            $rows[] = [
                $alert->id ?? '-',
                $this->truncate($alert->subject ?? '', 60),
                strtoupper($alert->status ?? ''),
                $this->formatTimestamp($alert->createTime ?? null),
            ];
        }

        $this->table(['Alert ID', 'Subject', 'Status', 'Created'], $rows);
    }

    /**
     * @param ListAlertDetailsResponseDTO $details
     * @return void
     */
    private function renderAlertDetail(ListAlertDetailsResponseDTO $details): void
    {
        $rows = [
            ['Alert ID', $details->id ?? '-'],
            ['Subject', $details->subject ?? '-'],
            ['Symbol', $details->symbol ?? '-'],
            ['Created', $this->formatTimestamp($details->createTime ?? null)],
            ['Read', $this->formatTimestamp($details->readTime ?? null)],
            ['Deleted', $this->formatTimestamp($details->deleteTime ?? null)],
            ['Message', $this->truncate($details->msgText ?? '', 120)],
        ];

        $this->table(['Field', 'Value'], $rows);
    }

    /**
     * @param OrdersResponseDTO $orders
     * @return void
     */
    private function renderOrders(OrdersResponseDTO $orders): void
    {
        if (empty($orders->order)) {
            $this->warn('No orders returned.');
            return;
        }

        $rows = [];
        foreach ($orders->order as $order) {
            $detail = $order->orderDetail[0] ?? null;
            $instrument = $detail->instrument[0] ?? null;
            $rows[] = [
                $order->orderId ?? '-',
                strtoupper($detail->status ?? ''),
                strtoupper($instrument?->orderAction ?? ''),
                $instrument?->product?->symbol ?? '-',
                $this->formatNumber($instrument?->orderedQuantity),
                $this->formatPriceForDetail($detail),
            ];
        }

        $this->table(['Order ID', 'Status', 'Action', 'Symbol', 'Qty', 'Price'], $rows);
    }

    /**
     * @param PreviewOrderResponseDTO $preview
     * @return void
     */
    private function renderOrderPreview(PreviewOrderResponseDTO $preview): void
    {
        $previewIds = collect($preview->previewIds ?? [])->map(fn ($dto) => $dto->previewId ?? null)->filter()->all();
        $rows = [
            ['Order Type', strtoupper($preview->orderType ?? '')],
            ['Total Value', $this->formatMoney($preview->totalOrderValue)],
            ['Commission', $this->formatMoney($preview->totalCommission)],
            ['Account', $preview->accountId ?? '-'],
            ['Preview IDs', empty($previewIds) ? '-' : implode(', ', $previewIds)],
        ];

        $this->table(['Field', 'Value'], $rows);
        $this->renderOrderLegs($preview->order);
    }

    /**
     * @param PlaceOrderResponseDTO $order
     * @return void
     */
    private function renderOrderPlacement(PlaceOrderResponseDTO $order): void
    {
        $firstOrderId = $order->orderIds[0] ?? null;
        $orderId = $order->orderId ?? ($firstOrderId?->orderId ?? null);
        $rows = [
            ['Order ID', $orderId ?? '-'],
            ['Order Type', strtoupper($order->orderType ?? '')],
            ['Total Value', $this->formatMoney($order->totalOrderValue)],
            ['Commission', $this->formatMoney($order->totalCommission)],
            ['Placed', $this->formatTimestamp($order->placedTime ?? null)],
        ];

        $this->table(['Field', 'Value'], $rows);
        $this->renderOrderLegs($order->order);
    }

    /**
     * @param CancelOrderResponseDTO $cancelled
     * @return void
     */
    private function renderCancelledOrder(CancelOrderResponseDTO $cancelled): void
    {
        $rows = [
            ['Account', $cancelled->accountId ?? '-'],
            ['Order ID', $cancelled->orderId ?? '-'],
            ['Cancelled At', $this->formatTimestamp($cancelled->cancelTime ?? null)],
        ];

        $this->table(['Field', 'Value'], $rows);
    }

    /**
     * @param DeleteAlertsResponseDTO $response
     * @return void
     */
    private function renderDeleteAlerts(DeleteAlertsResponseDTO $response): void
    {
        $rows = [
            ['Result', $response->result ?? '-'],
            ['Failed IDs', empty($response->failedAlerts?->alertId ?? []) ? '-' : implode(', ', $response->failedAlerts->alertId)],
        ];

        $this->table(['Field', 'Value'], $rows);
    }

    /**
     * @param array $orderDetails
     * @return void
     */
    private function renderOrderLegs(array $orderDetails): void
    {
        if (empty($orderDetails)) {
            return;
        }

        $rows = [];
        foreach ($orderDetails as $detail) {
            if (!$detail) {
                continue;
            }

            foreach ($detail->instrument ?? [] as $instrument) {
                $rows[] = [
                    strtoupper($instrument->orderAction ?? ''),
                    $instrument->product?->symbol ?? $instrument->symbolDescription ?? '-',
                    $this->formatNumber($instrument->quantity),
                    strtoupper($detail->priceType ?? ''),
                    $this->formatPriceForDetail($detail),
                    strtoupper($detail->status ?? ''),
                ];
            }
        }

        if (empty($rows)) {
            return;
        }

        $this->table(['Action', 'Symbol', 'Qty', 'Price Type', 'Price/Stop', 'Status'], $rows);
    }

    /**
     * @param array $pairs
     * @return array
     */
    private function sortOptionPairsByStrike(array $pairs): array
    {
        usort($pairs, function ($a, $b) {
            $strikeA = $a->call?->strikePrice ?? $a->put?->strikePrice ?? 0;
            $strikeB = $b->call?->strikePrice ?? $b->put?->strikePrice ?? 0;
            return $strikeA <=> $strikeB;
        });

        return $pairs;
    }

    /**
     * @param mixed $payload
     * @return void
     */
    private function renderFallbackTable(mixed $payload): void
    {
        $data = is_object($payload) && method_exists($payload, 'toArray')
            ? $payload->toArray()
            : (array) $payload;

        $rows = [];
        foreach (array_slice($data, 0, 10, true) as $key => $value) {
            $rows[] = [$key, $this->stringifyValue($value)];
        }

        $this->table(['Key', 'Value'], $rows);
    }

    /**
     * @param float|null $value
     * @return string
     */
    private function formatMoney(?float $value): string
    {
        return $value === null ? '-' : number_format($value, 2);
    }

    /**
     * @param float|int|null $value
     * @param int $decimals
     * @return string
     */
    private function formatNumber(float|int|null $value, int $decimals = 2): string
    {
        return $value === null ? '-' : number_format($value, $decimals);
    }

    /**
     * @param float|null $value
     * @return string
     */
    private function formatPercent(?float $value): string
    {
        return $value === null ? '-' : number_format($value, 2) . '%';
    }

    /**
     * @param float|null $bid
     * @param float|null $ask
     * @return string
     */
    private function formatBidAsk(?float $bid, ?float $ask): string
    {
        if ($bid === null && $ask === null) {
            return '-';
        }

        return trim($this->formatMoney($bid) . ' / ' . $this->formatMoney($ask), ' /');
    }

    /**
     * @param int|null $callVolume
     * @param int|null $putVolume
     * @return string
     */
    private function formatVolumePair(?int $callVolume, ?int $putVolume): string
    {
        if ($callVolume === null && $putVolume === null) {
            return '-';
        }

        return sprintf(
            '%s / %s',
            $this->formatNumber($callVolume, 0),
            $this->formatNumber($putVolume, 0)
        );
    }

    /**
     * @param object|null $detail
     * @return string
     */
    private function formatPriceForDetail(?object $detail): string
    {
        if ($detail === null) {
            return '-';
        }

        $price = $detail->limitPrice ?? $detail->stopPrice ?? $detail->stopLimitPrice ?? $detail->priceValue ?? null;

        if ($price !== null) {
            if (is_numeric($price)) {
                return $this->formatMoney((float) $price);
            }

            return strtoupper((string) $price);
        }

        return strtoupper((string) ($detail->priceType ?? 'MKT'));
    }

    /**
     * @param Carbon|int|null $timestamp
     * @return string
     */
    private function formatTimestamp(Carbon|int|null $timestamp): string
    {
        if ($timestamp === null) {
            return '-';
        }

        if ($timestamp instanceof Carbon) {
            return $timestamp->toDateTimeString();
        }

        try {
            $seconds = $timestamp > 9999999999 ? $timestamp / 1000 : $timestamp;
            return Carbon::createFromTimestamp((int) $seconds)->toDateTimeString();
        } catch (Throwable) {
            return (string) $timestamp;
        }
    }

    /**
     * @param string|null $value
     * @param int $length
     * @return string
     */
    private function truncate(?string $value, int $length = 80): string
    {
        if ($value === null) {
            return '-';
        }

        return strlen($value) > $length ? substr($value, 0, $length - 3) . '...' : $value;
    }

    /**
     * @param mixed $value
     * @return string
     */
    private function stringifyValue(mixed $value): string
    {
        if ($value instanceof Carbon) {
            return $value->toDateTimeString();
        }

        if (is_scalar($value) || $value === null) {
            return (string) ($value ?? '-');
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '-';
    }

    /**
     * @param mixed $accounts
     * @return string|null
     */
    private function pickAccountFromList(mixed $accounts): ?string
    {
        if (!$accounts || empty($accounts->accounts ?? [])) {
            return null;
        }

        $options = [];
        foreach ($accounts->accounts as $account) {
            $key = $account->accountIdKey ?? '';
            $options[$key] = "{$account->accountName} ({$account->accountId})";
        }

        $selected = $this->choice('Select an account', array_map(fn ($label, $key) => "{$label} [{$key}]", $options, array_keys($options)));
        preg_match('/\[(.+)]$/', $selected, $matches);

        return $matches[1] ?? null;
    }

    /**
     * @return string|null
     */
    private function resolveAccountIdKey(): ?string
    {
        try {
            $accounts = $this->apiClient->getAccountList();
        } catch (Throwable $e) {
            $this->reportError('Unable to fetch account list', $e);
            return null;
        }

        return $this->pickAccountFromList($accounts);
    }

    /**
     * @param string $scenario
     * @param string $accountIdKey
     * @return EtradeOrderBuilder|null
     * @throws RandomException
     */
    private function buildOrderByScenario(string $scenario, string $accountIdKey): ?EtradeOrderBuilder
    {
        $symbol = strtoupper($this->ask('Symbol', 'SPY'));
        $quantity = (float) $this->ask('Quantity', '1');
        $limit = (float) $this->ask('Limit price', '2.00');
        $needsOptions = $scenario !== 'equity';
        $defaultExpiry = $this->defaultOptionExpiryDate();
        $expiryYear = $needsOptions ? (int) $this->ask('Option expiry year', (string) $defaultExpiry->year) : null;
        $expiryMonth = $needsOptions ? (int) $this->ask('Option expiry month (1-12)', (string) $defaultExpiry->month) : null;
        $expiryDay = $needsOptions ? (int) $this->ask('Option expiry day', (string) $defaultExpiry->day) : null;

        $baseBuilder = EtradeOrderBuilder::forAccount($accountIdKey)
            ->clientOrderId($this->randomOrderId())
            ->quantityType('QUANTITY')
            ->gfd()
            ->marketSession('REGULAR');

        return match ($scenario) {
            'equity' => $baseBuilder
                ->orderType('EQ')
                ->withSymbol($symbol)
                ->priceType('LIMIT')
                ->limitPrice($limit)
                ->addEquity('BUY', $quantity),

            'single-option' => $baseBuilder
                ->orderType('OPTN')
                ->withSymbol($symbol)
                ->withExpiry($expiryYear, $expiryMonth, $expiryDay)
                ->priceType('LIMIT')
                ->limitPrice($limit)
                ->addLongCall(
                    (float) $this->ask('Call strike', '200'),
                    $quantity
                ),

            'vertical' => $baseBuilder
                ->orderType('SPREADS')
                ->withSymbol($symbol)
                ->withExpiry($expiryYear, $expiryMonth, $expiryDay)
                ->netDebit($limit)
                ->addLongCall((float) $this->ask('Long call strike', '180'), $quantity)
                ->addShortCall((float) $this->ask('Short call strike', '185'), $quantity),

            'three-leg' => $baseBuilder
                ->orderType('SPREADS')
                ->withSymbol($symbol)
                ->withExpiry($expiryYear, $expiryMonth, $expiryDay)
                ->netDebit($limit)
                ->addLongCall((float) $this->ask('Long call strike', '180'), $quantity)
                ->addShortCall((float) $this->ask('Short call strike', '185'), $quantity)
                ->addShortPut((float) $this->ask('Short put strike', '120'), $quantity),

            'iron-condor' => $baseBuilder
                ->orderType('SPREADS')
                ->withSymbol($symbol)
                ->withExpiry($expiryYear, $expiryMonth, $expiryDay)
                ->netCredit($limit)
                ->addShortPut((float) $this->ask('Short put strike', '110'), $quantity)
                ->addLongPut((float) $this->ask('Long put strike', '105'), $quantity)
                ->addShortCall((float) $this->ask('Short call strike', '190'), $quantity)
                ->addLongCall((float) $this->ask('Long call strike', '195'), $quantity),

            'buy-write' => $baseBuilder
                ->orderType('SPREADS')
                ->withSymbol($symbol)
                ->priceType('LIMIT')
                ->limitPrice($limit)
                ->addEquity('BUY', $quantity)
                ->withExpiry($expiryYear, $expiryMonth, $expiryDay)
                ->addShortCall((float) $this->ask('Covered call strike', '210'), $quantity),

            'collar' => $baseBuilder
                ->orderType('SPREADS')
                ->withSymbol($symbol)
                ->priceType('LIMIT')
                ->limitPrice($limit)
                ->addEquity('BUY', $quantity)
                ->withExpiry($expiryYear, $expiryMonth, $expiryDay)
                ->addLongPut((float) $this->ask('Protective put strike', '120'), $quantity)
                ->addShortCall((float) $this->ask('Covered call strike', '210'), $quantity),

            default => null,
        };
    }

    /**
     * @param EtradeOrderBuilder $builder
     * @return float
     */
    private function extractLimitPrice(EtradeOrderBuilder $builder): float
    {
        $reflection = new \ReflectionClass($builder);
        $prop = $reflection->getProperty('orderDetailFields');
        $fields = $prop->getValue($builder);

        return (float) ($fields['limitPrice'] ?? $fields['stopLimitPrice'] ?? $fields['stopPrice'] ?? 0);
    }

    /**
     * @param EtradeOrderBuilder $builder
     * @return string
     */
    private function extractAccountId(EtradeOrderBuilder $builder): string
    {
        $reflection = new \ReflectionClass($builder);
        $prop = $reflection->getProperty('accountIdKey');

        return (string) $prop->getValue($builder);
    }

    /**
     * @param EtradeOrderBuilder $builder
     * @param float $newLimit
     * @return EtradeOrderBuilder
     * @throws ReflectionException
     */
    private function cloneBuilderWithNewLimit(EtradeOrderBuilder $builder, float $newLimit): EtradeOrderBuilder
    {
        $reflection = new \ReflectionClass($builder);
        /** @var EtradeOrderBuilder $clone */
        $clone = $reflection->newInstanceWithoutConstructor();
        foreach ($reflection->getProperties() as $property) {
            $property->setValue($clone, $property->getValue($builder));
        }

        $clone->limitPrice($newLimit);

        return $clone;
    }

    /**
     * @return Carbon
     */
    private function defaultOptionExpiryDate(): Carbon
    {
        $threshold = now()->addMonths(2);
        $candidateMonth = $threshold->copy();

        do {
            $candidate = $this->thirdFridayOfMonth($candidateMonth->year, $candidateMonth->month);
            if ($candidate->greaterThanOrEqualTo($threshold)) {
                return $candidate;
            }

            $candidateMonth->addMonth();
        } while (true);
    }

    /**
     * @param int $year
     * @param int $month
     * @return Carbon
     */
    private function thirdFridayOfMonth(int $year, int $month): Carbon
    {
        return Carbon::create($year, $month)->nthOfMonth(3, CarbonInterface::FRIDAY);
    }

    /**
     * @param string $question
     * @return bool
     */
    private function confirmDanger(string $question): bool
    {
        if (!$this->confirm($question)) {
            return false;
        }
        $additionalWarning = $this->apiClient->isProduction()
            ? ' 🚨🚨🚨 This action will be executed on your live E*TRADE account! 🚨🚨🚨'
            : '';
        return $this->confirm('Really proceed?' . $additionalWarning);
    }

    /**
     * @param string $label
     * @param callable $callback
     * @return mixed
     */
    private function withApiCall(string $label, callable $callback): mixed
    {
        $this->line(PHP_EOL . "-> {$label}");

        try {
            return $callback();
        } catch (Throwable $e) {
            $this->reportError($label, $e);
            return null;
        }
    }

    /**
     * @param string $context
     * @param Throwable $e
     * @return void
     */
    private function reportError(string $context, Throwable $e): void
    {
        $message = $e instanceof EtradeApiException || $e instanceof GuzzleException
            ? $e->getMessage()
            : ($e->getMessage() ?: get_class($e));

        $this->error("{$context} failed: {$message}");
    }

    /**
     * @param string $title
     * @return void
     */
    private function section(string $title): void
    {
        $this->line('');
        $this->info($title);
    }

    /**
     * @param string $title
     * @return void
     */
    private function subSection(string $title): void
    {
        $this->line('');
        $this->comment("{$title} ----------------");
    }

    /**
     * @return string
     * @throws RandomException
     */
    private function randomOrderId(): string
    {
        return 'test'. bin2hex(random_bytes(8));
    }

    /**
     * @return bool
     */
    private function hasActiveAuthToken(): bool
    {
        try {
            $this->apiClient->getAccessToken();
            return true;
        } catch (Throwable) {
            $this->error('No active access token found. Please authenticate first via the auth option.');
            return false;
        }
    }
}
