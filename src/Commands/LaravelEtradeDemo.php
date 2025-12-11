<?php

namespace KevinRider\LaravelEtrade\Commands;

use Carbon\CarbonInterface;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
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
use KevinRider\LaravelEtrade\EtradeApiClient;
use KevinRider\LaravelEtrade\EtradeOrderBuilder;
use KevinRider\LaravelEtrade\Exceptions\EtradeApiException;
use Random\RandomException;
use ReflectionException;
use Throwable;

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
        $this->renderJson($balanceDto?->toArray());

        $portfolio = $this->withApiCall('Portfolio (holdings)', function () use ($accountIdKey) {
            return $this->apiClient->getViewPortfolio(new ViewPortfolioRequestDTO([
                'accountIdKey' => $accountIdKey,
                'view' => 'PERFORMANCE',
                'totalsRequired' => true,
            ]));
        });
        $this->renderJson($portfolio?->toArray());

        $transactions = $this->withApiCall('Recent transactions', function () use ($accountIdKey) {
            return $this->apiClient->getAccountTransactions(new ListTransactionsRequestDTO([
                'accountIdKey' => $accountIdKey,
                'count' => 10,
                'sortOrder' => 'DESC',
            ]));
        });
        $this->renderJson($transactions?->toArray());

        if ($transactions && !empty($transactions->transactions)) {
            $firstId = $transactions->transactions[0]->transactionId ?? null;
            if ($firstId && $this->confirm("Fetch details for transaction {$firstId}?")) {
                $details = $this->withApiCall('Transaction details', function () use ($accountIdKey, $firstId) {
                    return $this->apiClient->getAccountTransactionDetails(new ListTransactionDetailsRequestDTO([
                        'accountIdKey' => $accountIdKey,
                        'transactionId' => $firstId,
                    ]));
                });
                $this->renderJson($details?->toArray());
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
        $this->renderJson($lookup?->toArray());

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
            $this->renderJson($quotes?->toArray());
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
        $this->renderJson($expirations?->toArray());

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
        $this->renderJson($chains?->toArray());
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
        $this->renderJson($alerts?->toArray());

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
                $this->renderJson($details?->toArray());
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
        $this->renderJson($orders?->toArray());
    }

    /**
     * @return void
     * @throws RandomException
     * @throws ReflectionException
     */
    private function runOrderMenu(): void
    {
        $this->section('Order lifecycle demos. 🚨🚨🚨 Orders will be placed against your live E*Trade account! 🚨🚨🚨');

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

        $this->renderJson($previewResponse->toArray());

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

        $this->renderJson($placeResponse->toArray());

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
            $this->renderJson($changePreview?->toArray());

            $changePreviewIds = collect($changePreview?->previewIds ?? [])->map(fn ($dto) => $dto->previewId ?? null)->filter()->all();
            if (!empty($changePreviewIds) && $this->confirmDanger('Place the change order live?')) {
                $changePlaced = $this->withApiCall('Placing change order', fn () => $this->apiClient->placeChangeOrder($builder->buildPlaceRequest($changePreviewIds)));
                $placedOrderId = $changePlaced->orderId ?? ($changePlaced->orderIds[0]->orderId ?? null);
                $this->renderJson($changePlaced?->toArray());
            }
        }

        if ($this->confirmDanger('Cancel this order now? (destructive)')) {
            $cancelled = $this->withApiCall('Cancel order', function () use ($builder, $placedOrderId) {
                return $this->apiClient->cancelOrder(new CancelOrderRequestDTO([
                    'accountIdKey' => $this->extractAccountId($builder),
                    'orderId' => $placedOrderId,
                ]));
            });
            $this->renderJson($cancelled?->toArray());
        }
    }

    /**
     * @return void
     */
    private function runDestructiveMenu(): void
    {
        $this->section('Destructive operations (extra prompts)');

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
        $this->renderJson($alerts?->toArray());

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
        $this->renderJson($deleted?->toArray());
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
        $this->renderJson($cancelled?->toArray());
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
    private function renderJson(mixed $payload): void
    {
        if ($payload === null) {
            return;
        }

        $this->line(json_encode($payload, JSON_PRETTY_PRINT));
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
        $limit = (float) $this->ask('Limit price (kept unrealistically low/high to avoid fills)', '2.00');
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

        return $this->confirm('🚨🚨🚨 Really proceed? This will hit your live E*TRADE account! 🚨🚨🚨');
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
