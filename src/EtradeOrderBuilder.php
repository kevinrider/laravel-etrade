<?php

namespace KevinRider\LaravelEtrade;

use InvalidArgumentException;
use KevinRider\LaravelEtrade\Dtos\Orders\DisclosureDTO;
use KevinRider\LaravelEtrade\Dtos\Orders\InstrumentDTO;
use KevinRider\LaravelEtrade\Dtos\Orders\OrderDetailDTO;
use KevinRider\LaravelEtrade\Dtos\Orders\PreviewIdDTO;
use KevinRider\LaravelEtrade\Dtos\Request\PlaceOrderRequestDTO;
use KevinRider\LaravelEtrade\Dtos\Request\PreviewOrderRequestDTO;

class EtradeOrderBuilder
{
    private const array VALID_QUANTITY_TYPES = ['QUANTITY', 'DOLLAR', 'ALL_I_OWN'];
    private const array VALID_ORDER_TERMS = [
        'GOOD_UNTIL_CANCEL',
        'GOOD_FOR_DAY',
        'GOOD_TILL_DATE',
        'IMMEDIATE_OR_CANCEL',
        'FILL_OR_KILL',
    ];
    private const array VALID_ORDER_TYPES = [
        'EQ',
        'OPTN',
        'SPREADS',
        'BUY_WRITES',
        'BUTTERFLY',
        'IRON_BUTTERFLY',
        'CONDOR',
        'IRON_CONDOR',
        'MF',
        'MMF',
    ];
    private const array VALID_PRICE_TYPES = [
        'MARKET',
        'LIMIT',
        'STOP',
        'STOP_LIMIT',
        'TRAILING_STOP_CNST_BY_LOWER_TRIGGER',
        'UPPER_TRIGGER_BY_TRAILING_STOP_CNST',
        'TRAILING_STOP_PRCT_BY_LOWER_TRIGGER',
        'UPPER_TRIGGER_BY_TRAILING_STOP_PRCT',
        'TRAILING_STOP_CNST',
        'TRAILING_STOP_PRCT',
        'HIDDEN_STOP',
        'HIDDEN_STOP_BY_LOWER_TRIGGER',
        'UPPER_TRIGGER_BY_HIDDEN_STOP',
        'NET_DEBIT',
        'NET_CREDIT',
        'NET_EVEN',
        'MARKET_ON_OPEN',
        'MARKET_ON_CLOSE',
        'LIMIT_ON_OPEN',
        'LIMIT_ON_CLOSE',
    ];
    private const array VALID_MARKET_SESSIONS = ['REGULAR', 'EXTENDED'];
    private const array VALID_ORDER_ACTIONS = [
        'BUY',
        'SELL',
        'BUY_TO_COVER',
        'SELL_SHORT',
        'BUY_OPEN',
        'BUY_CLOSE',
        'SELL_OPEN',
        'SELL_CLOSE',
        'EXCHANGE',
    ];
    private const array VALID_SECURITY_TYPES = ['EQ', 'OPTN', 'MF', 'MMF'];

    private ?string $accountIdKey = null;
    private ?string $orderType = null;
    private ?string $clientOrderId = null;
    private ?string $defaultSymbol = null;
    private ?int $defaultExpiryYear = null;
    private ?int $defaultExpiryMonth = null;
    private ?int $defaultExpiryDay = null;
    private ?int $orderId = null;
    private string $defaultSecurityType = 'OPTN';
    private ?string $defaultQuantityType = null;

    /**
     * @var InstrumentDTO[]
     */
    private array $instruments = [];

    /**
     * @var array<string, mixed>
     */
    private array $orderDetailFields = [];

    /**
     * @param string $accountIdKey
     * @return self
     */
    public static function forAccount(string $accountIdKey): self
    {
        $instance = new self();
        $instance->accountIdKey = $accountIdKey;

        return $instance;
    }

    /**
     * @param string $orderType
     * @return $this
     */
    public function orderType(string $orderType): self
    {
        $this->assertValidEnum($orderType, self::VALID_ORDER_TYPES, 'orderType');
        $this->orderType = $orderType;
        return $this;
    }

    /**
     * @param string $clientOrderId
     * @return $this
     */
    public function clientOrderId(string $clientOrderId): self
    {
        $this->clientOrderId = $clientOrderId;
        return $this;
    }

    /**
     * @param int $orderId
     * @return $this
     */
    public function orderId(int $orderId): self
    {
        $this->orderId = $orderId;
        return $this;
    }

    /**
     * @param string $symbol
     * @return $this
     */
    public function withSymbol(string $symbol): self
    {
        $this->defaultSymbol = $symbol;
        return $this;
    }

    /**
     * @param int $year
     * @param int $month
     * @param int $day
     * @return $this
     */
    public function withExpiry(int $year, int $month, int $day): self
    {
        $this->assertValidExpiryDate($year, $month, $day);
        $this->defaultExpiryYear = $year;
        $this->defaultExpiryMonth = $month;
        $this->defaultExpiryDay = $day;
        return $this;
    }

    /**
     * @param string $quantityType
     * @return $this
     */
    public function quantityType(string $quantityType): self
    {
        $this->assertValidEnum($quantityType, self::VALID_QUANTITY_TYPES, 'quantityType');
        $this->defaultQuantityType = $quantityType;
        return $this;
    }

    /**
     * @param string $orderTerm
     * @return $this
     */
    public function term(string $orderTerm): self
    {
        $this->assertValidEnum($orderTerm, self::VALID_ORDER_TERMS, 'orderTerm');
        $this->orderDetailFields['orderTerm'] = $orderTerm;
        return $this;
    }

    /**
     * @return $this
     */
    public function gfd(): self
    {
        return $this->term('GOOD_FOR_DAY');
    }

    /**
     * @return $this
     */
    public function gtc(): self
    {
        return $this->term('GOOD_UNTIL_CANCEL');
    }

    /**
     * @param string $priceType
     * @return $this
     */
    public function priceType(string $priceType): self
    {
        $this->assertValidEnum($priceType, self::VALID_PRICE_TYPES, 'priceType');
        $this->orderDetailFields['priceType'] = $priceType;
        return $this;
    }

    /**
     * @param float $limitPrice
     * @return self
     */
    public function netCredit(float $limitPrice): self
    {
        return $this->priceType('NET_CREDIT')->limitPrice($limitPrice);
    }

    /**
     * @param float $limitPrice
     * @return self
     */
    public function netDebit(float $limitPrice): self
    {
        return $this->priceType('NET_DEBIT')->limitPrice($limitPrice);
    }

    /**
     * @return $this
     */
    public function market(): self
    {
        return $this->priceType('MARKET');
    }

    /**
     * @param float $limitPrice
     * @return $this
     */
    public function limitPrice(float $limitPrice): self
    {
        $this->assertPositiveFloat($limitPrice, 'limitPrice');
        $this->orderDetailFields['limitPrice'] = $limitPrice;
        return $this;
    }

    /**
     * @param float $stopPrice
     * @return $this
     */
    public function stopPrice(float $stopPrice): self
    {
        $this->assertNonNegativeFloat($stopPrice, 'stopPrice');
        $this->orderDetailFields['stopPrice'] = $stopPrice;
        return $this;
    }

    /**
     * @param float $stopLimitPrice
     * @return $this
     */
    public function stopLimitPrice(float $stopLimitPrice): self
    {
        $this->assertPositiveFloat($stopLimitPrice, 'stopLimitPrice');
        $this->orderDetailFields['stopLimitPrice'] = $stopLimitPrice;
        return $this;
    }

    /**
     * @param string $marketSession
     * @return $this
     */
    public function marketSession(string $marketSession): self
    {
        $this->assertValidEnum($marketSession, self::VALID_MARKET_SESSIONS, 'marketSession');
        $this->orderDetailFields['marketSession'] = $marketSession;
        return $this;
    }

    /**
     * @param bool $allOrNone
     * @return $this
     */
    public function allOrNone(bool $allOrNone): self
    {
        $this->orderDetailFields['allOrNone'] = $allOrNone;
        return $this;
    }

    /**
     * @param DisclosureDTO $disclosure
     * @return $this
     */
    public function disclosure(DisclosureDTO $disclosure): self
    {
        $this->orderDetailFields['disclosure'] = $disclosure->toArray();
        return $this;
    }

    /**
     * Add a leg/instrument to the order.
     *
     * @param InstrumentDTO|array $instrument
     * @return $this
     */
    public function addInstrument(InstrumentDTO|array $instrument): self
    {
        $data = $instrument instanceof InstrumentDTO ? $instrument->toArray() : $instrument;
        $data['quantityType'] = $this->resolveQuantityType($data['quantityType'] ?? null);

        $this->instruments[] = new InstrumentDTO($data);
        return $this;
    }

    /**
     * @param float $strikePrice
     * @param float $quantity
     * @param array $overrides
     * @return self
     */
    public function addLongCall(float $strikePrice, float $quantity = 1, array $overrides = []): self
    {
        return $this->addOptionLeg('CALL', 'BUY_OPEN', $strikePrice, $quantity, $overrides);
    }

    /**
     * @param float $strikePrice
     * @param float $quantity
     * @param array $overrides
     * @return self
     */
    public function addShortCall(float $strikePrice, float $quantity = 1, array $overrides = []): self
    {
        return $this->addOptionLeg('CALL', 'SELL_OPEN', $strikePrice, $quantity, $overrides);
    }

    /**
     * @param float $strikePrice
     * @param float $quantity
     * @param array $overrides
     * @return self
     */
    public function addLongPut(float $strikePrice, float $quantity = 1, array $overrides = []): self
    {
        return $this->addOptionLeg('PUT', 'BUY_OPEN', $strikePrice, $quantity, $overrides);
    }

    /**
     * @param float $strikePrice
     * @param float $quantity
     * @param array $overrides
     * @return self
     */
    public function addShortPut(float $strikePrice, float $quantity = 1, array $overrides = []): self
    {
        return $this->addOptionLeg('PUT', 'SELL_OPEN', $strikePrice, $quantity, $overrides);
    }

    /**
     * @param string $orderAction
     * @param float $quantity
     * @param array $overrides
     * @return $this
     */
    public function addEquity(string $orderAction, float $quantity = 1, array $overrides = []): self
    {
        $this->assertValidEnum($orderAction, self::VALID_ORDER_ACTIONS, 'orderAction');
        $this->assertPositiveFloat($quantity, 'quantity');
        $symbol = $overrides['symbol'] ?? $this->defaultSymbol;
        $securityType = $overrides['securityType'] ?? 'EQ';

        if (!$symbol) {
            throw new InvalidArgumentException('Symbol is required for equity legs. Use withSymbol() or pass symbol override.');
        }

        $this->assertValidEnum($securityType, self::VALID_SECURITY_TYPES, 'securityType');

        $instrument = [
            'orderAction' => $orderAction,
            'quantityType' => $overrides['quantityType'] ?? null,
            'quantity' => $quantity,
            'orderedQuantity' => $overrides['orderedQuantity'] ?? null,
            'product' => [
                'symbol' => $symbol,
                'securityType' => $securityType,
            ],
        ];

        return $this->addInstrument($instrument);
    }

    /**
     * Add or override any order detail field (for fields without explicit helpers).
     *
     * @param array<string, mixed> $fields
     * @return $this
     */
    public function withDetail(array $fields): self
    {
        $this->orderDetailFields = array_merge($this->orderDetailFields, $fields);
        return $this;
    }

    /**
     * @return PreviewOrderRequestDTO
     */
    public function buildPreviewRequest(): PreviewOrderRequestDTO
    {
        $this->assertRequiredForPreview();

        $detailPayload = $this->sanitizeDetailPayload(
            array_merge(
                $this->orderDetailFields,
                ['instrument' => $this->instruments]
            )
        );

        return new PreviewOrderRequestDTO([
            'accountIdKey' => $this->accountIdKey,
            'orderType' => $this->orderType,
            'clientOrderId' => $this->clientOrderId,
            'orderId' => $this->orderId,
            'order' => [new OrderDetailDTO($detailPayload)],
        ]);
    }

    /**
     * @param array<int, PreviewIdDTO|array|int> $previewIds
     */
    public function buildPlaceRequest(array $previewIds): PlaceOrderRequestDTO
    {
        $this->assertRequiredForPlace($previewIds);

        $detailPayload = $this->sanitizeDetailPayload(
            array_merge(
                $this->orderDetailFields,
                ['instrument' => $this->instruments]
            )
        );

        $normalizedPreviewIds = array_map(function ($id) {
            if ($id instanceof PreviewIdDTO) {
                return $id;
            }
            if (is_array($id)) {
                if (!array_key_exists('previewId', $id)) {
                    throw new InvalidArgumentException('previewId must be provided for each previewIds entry.');
                }
                return new PreviewIdDTO($id);
            }
            if (is_int($id)) {
                return new PreviewIdDTO(['previewId' => $id]);
            }
            throw new InvalidArgumentException('previewIds must be PreviewIdDTO, array with previewId, or integer preview id.');
        }, $previewIds);

        return new PlaceOrderRequestDTO([
            'accountIdKey' => $this->accountIdKey,
            'orderType' => $this->orderType,
            'clientOrderId' => $this->clientOrderId,
            'orderId' => $this->orderId,
            'order' => [new OrderDetailDTO($detailPayload)],
            'previewIds' => $normalizedPreviewIds,
        ]);
    }

    /**
     * @param array<string, mixed> $detailPayload
     * @return array<string, mixed>
     */
    private function sanitizeDetailPayload(array $detailPayload): array
    {
        if (array_key_exists('stopPrice', $detailPayload) && $detailPayload['stopPrice'] === '') {
            $detailPayload['stopPrice'] = null;
        }

        return $detailPayload;
    }

    /**
     * @param string $callPut
     * @param string $orderAction
     * @param float $strikePrice
     * @param float $quantity
     * @param array<string, mixed> $overrides
     * @return self
     */
    private function addOptionLeg(string $callPut, string $orderAction, float $strikePrice, float $quantity, array $overrides): self
    {
        $this->assertValidEnum($orderAction, self::VALID_ORDER_ACTIONS, 'orderAction');
        $this->assertPositiveFloat($strikePrice, 'strikePrice');
        $this->assertPositiveFloat($quantity, 'quantity');
        $symbol = $overrides['symbol'] ?? $this->defaultSymbol;
        $expiryYear = $overrides['expiryYear'] ?? $this->defaultExpiryYear;
        $expiryMonth = $overrides['expiryMonth'] ?? $this->defaultExpiryMonth;
        $expiryDay = $overrides['expiryDay'] ?? $this->defaultExpiryDay;
        $securityType = $overrides['securityType'] ?? $this->defaultSecurityType;

        if (!$symbol) {
            throw new InvalidArgumentException('Symbol is required for option legs. Use withSymbol() or pass symbol override.');
        }
        if ($expiryYear === null || $expiryMonth === null || $expiryDay === null) {
            throw new InvalidArgumentException('Expiry year, month, and day are required for option legs. Use withExpiry() or pass expiry overrides.');
        }

        $this->assertValidExpiryDate($expiryYear, $expiryMonth, $expiryDay);
        $this->assertValidEnum($securityType, self::VALID_SECURITY_TYPES, 'securityType');

        $instrument = [
            'orderAction' => $orderAction,
            'quantityType' => $overrides['quantityType'] ?? $this->defaultQuantityType,
            'quantity' => $quantity,
            'orderedQuantity' => $overrides['orderedQuantity'] ?? $quantity,
            'product' => [
                'symbol' => $symbol,
                'securityType' => $securityType,
                'callPut' => $callPut,
                'expiryYear' => $expiryYear,
                'expiryMonth' => $expiryMonth,
                'expiryDay' => $expiryDay,
                'strikePrice' => $strikePrice,
            ],
        ];

        $this->addInstrument($instrument);

        return $this;
    }

    /**
     * @param string|null $quantityType
     * @return string|null
     */
    private function resolveQuantityType(?string $quantityType): ?string
    {
        if ($quantityType === null) {
            return $this->defaultQuantityType;
        }

        $this->assertValidEnum($quantityType, self::VALID_QUANTITY_TYPES, 'quantityType');

        return $quantityType;
    }

    /**
     * @param string $value
     * @param array<int, string> $allowed
     * @param string $field
     * @return void
     */
    private function assertValidEnum(string $value, array $allowed, string $field): void
    {
        if (!in_array($value, $allowed, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    '%s must be one of: %s',
                    $field,
                    implode(', ', $allowed)
                )
            );
        }
    }

    /**
     * @param int $year
     * @param int $month
     * @param int $day
     * @return void
     */
    private function assertValidExpiryDate(int $year, int $month, int $day): void
    {
        if (!checkdate($month, $day, $year)) {
            throw new InvalidArgumentException('Expiry date must be a valid calendar date.');
        }
    }

    /**
     * @param float $value
     * @param string $field
     * @return void
     */
    private function assertPositiveFloat(float $value, string $field): void
    {
        if ($value <= 0) {
            throw new InvalidArgumentException(sprintf('%s must be greater than 0.', $field));
        }
    }

    /**
     * @param float $value
     * @param string $field
     * @return void
     */
    private function assertNonNegativeFloat(float $value, string $field): void
    {
        if ($value < 0) {
            throw new InvalidArgumentException(sprintf('%s must be 0 or greater.', $field));
        }
    }

    /**
     * @return void
     */
    private function assertRequiredForPreview(): void
    {
        if (!$this->accountIdKey) {
            throw new InvalidArgumentException('accountIdKey is required.');
        }
        if (!$this->orderType) {
            throw new InvalidArgumentException('orderType is required.');
        }
        if (!$this->clientOrderId) {
            throw new InvalidArgumentException('clientOrderId is required.');
        }
        if (count($this->instruments) === 0) {
            throw new InvalidArgumentException('At least one instrument/leg is required.');
        }
    }

    /**
     * @param array $previewIds
     * @return void
     */
    private function assertRequiredForPlace(array $previewIds): void
    {
        $this->assertRequiredForPreview();
        if (count($previewIds) === 0) {
            throw new InvalidArgumentException('At least one previewId is required to place an order.');
        }
    }
}
