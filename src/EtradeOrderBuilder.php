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

    private ?string $accountIdKey = null;
    private ?string $orderType = null;
    private ?string $clientOrderId = null;
    private ?string $defaultSymbol = null;
    private ?int $defaultExpiryYear = null;
    private ?int $defaultExpiryMonth = null;
    private ?int $defaultExpiryDay = null;
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

    public static function forAccount(string $accountIdKey): self
    {
        $instance = new self();
        $instance->accountIdKey = $accountIdKey;

        return $instance;
    }

    public function orderType(string $orderType): self
    {
        $this->orderType = $orderType;
        return $this;
    }

    public function clientOrderId(string $clientOrderId): self
    {
        $this->clientOrderId = $clientOrderId;
        return $this;
    }

    public function withSymbol(string $symbol): self
    {
        $this->defaultSymbol = $symbol;
        return $this;
    }

    public function withExpiry(int $year, int $month, int $day): self
    {
        $this->defaultExpiryYear = $year;
        $this->defaultExpiryMonth = $month;
        $this->defaultExpiryDay = $day;
        return $this;
    }

    public function quantityType(string $quantityType): self
    {
        $this->assertValidQuantityType($quantityType);
        $this->defaultQuantityType = $quantityType;
        return $this;
    }

    public function term(string $orderTerm): self
    {
        $this->orderDetailFields['orderTerm'] = $orderTerm;
        return $this;
    }

    public function gfd(): self
    {
        return $this->term('GOOD_FOR_DAY');
    }

    public function gtc(): self
    {
        return $this->term('GOOD_UNTIL_CANCEL');
    }

    public function priceType(string $priceType): self
    {
        $this->orderDetailFields['priceType'] = $priceType;
        return $this;
    }

    public function netCredit(float $limitPrice): self
    {
        return $this->priceType('NET_CREDIT')->limitPrice($limitPrice);
    }

    public function netDebit(float $limitPrice): self
    {
        return $this->priceType('NET_DEBIT')->limitPrice($limitPrice);
    }

    public function market(): self
    {
        return $this->priceType('MARKET');
    }

    public function limitPrice(float $limitPrice): self
    {
        $this->orderDetailFields['limitPrice'] = $limitPrice;
        return $this;
    }

    public function stopPrice(float $stopPrice): self
    {
        $this->orderDetailFields['stopPrice'] = $stopPrice;
        return $this;
    }

    public function stopLimitPrice(float $stopLimitPrice): self
    {
        $this->orderDetailFields['stopLimitPrice'] = $stopLimitPrice;
        return $this;
    }

    public function marketSession(string $marketSession): self
    {
        $this->orderDetailFields['marketSession'] = $marketSession;
        return $this;
    }

    public function allOrNone(bool $allOrNone): self
    {
        $this->orderDetailFields['allOrNone'] = $allOrNone;
        return $this;
    }

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
        if (is_array($instrument)) {
            $instrument = new InstrumentDTO($instrument);
        }

        $instrument->quantityType = $this->resolveQuantityType($instrument->quantityType);
        $this->instruments[] = $instrument;
        return $this;
    }

    public function addLongCall(float $strikePrice, float $quantity = 1, array $overrides = []): self
    {
        return $this->addOptionLeg('CALL', 'BUY_OPEN', $strikePrice, $quantity, $overrides);
    }

    public function addShortCall(float $strikePrice, float $quantity = 1, array $overrides = []): self
    {
        return $this->addOptionLeg('CALL', 'SELL_OPEN', $strikePrice, $quantity, $overrides);
    }

    public function addLongPut(float $strikePrice, float $quantity = 1, array $overrides = []): self
    {
        return $this->addOptionLeg('PUT', 'BUY_OPEN', $strikePrice, $quantity, $overrides);
    }

    public function addShortPut(float $strikePrice, float $quantity = 1, array $overrides = []): self
    {
        return $this->addOptionLeg('PUT', 'SELL_OPEN', $strikePrice, $quantity, $overrides);
    }

    public function addEquity(string $orderAction, float $quantity = 1, array $overrides = []): self
    {
        $symbol = $overrides['symbol'] ?? $this->defaultSymbol;

        if (!$symbol) {
            throw new InvalidArgumentException('Symbol is required for equity legs. Use withSymbol() or pass symbol override.');
        }

        $instrument = [
            'orderAction' => $orderAction,
            'quantityType' => $overrides['quantityType'] ?? null,
            'quantity' => $quantity,
            'orderedQuantity' => $overrides['orderedQuantity'] ?? null,
            'product' => [
                'symbol' => $symbol,
                'securityType' => $overrides['securityType'] ?? 'EQ',
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
                return new PreviewIdDTO($id);
            }
            return new PreviewIdDTO(['previewId' => $id]);
        }, $previewIds);

        return new PlaceOrderRequestDTO([
            'accountIdKey' => $this->accountIdKey,
            'orderType' => $this->orderType,
            'clientOrderId' => $this->clientOrderId,
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
        $symbol = $overrides['symbol'] ?? $this->defaultSymbol;
        $expiryYear = $overrides['expiryYear'] ?? $this->defaultExpiryYear;
        $expiryMonth = $overrides['expiryMonth'] ?? $this->defaultExpiryMonth;
        $expiryDay = $overrides['expiryDay'] ?? $this->defaultExpiryDay;

        if (!$symbol) {
            throw new InvalidArgumentException('Symbol is required for option legs. Use withSymbol() or pass symbol override.');
        }
        if ($expiryYear === null || $expiryMonth === null || $expiryDay === null) {
            throw new InvalidArgumentException('Expiry year, month, and day are required for option legs. Use withExpiry() or pass expiry overrides.');
        }

        $instrument = [
            'orderAction' => $orderAction,
            'quantityType' => $overrides['quantityType'] ?? $this->defaultQuantityType,
            'quantity' => $quantity,
            'orderedQuantity' => $overrides['orderedQuantity'] ?? $quantity,
            'product' => [
                'symbol' => $symbol,
                'securityType' => $overrides['securityType'] ?? $this->defaultSecurityType,
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

    private function resolveQuantityType(?string $quantityType): ?string
    {
        if ($quantityType === null) {
            return $this->defaultQuantityType;
        }

        $this->assertValidQuantityType($quantityType);

        return $quantityType;
    }

    private function assertValidQuantityType(string $quantityType): void
    {
        if (!in_array($quantityType, self::VALID_QUANTITY_TYPES, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'quantityType must be one of: %s',
                    implode(', ', self::VALID_QUANTITY_TYPES)
                )
            );
        }
    }

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
