<?php

namespace KevinRider\LaravelEtrade\Dtos\ListOrders;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class InstrumentDTO extends BaseDTO
{
    public ?ProductDTO $product = null;
    public ?string $symbolDescription = null;
    public ?string $orderAction = null;
    public ?string $quantityType = null;
    public ?float $quantity = null;
    public ?float $cancelQuantity = null;
    public ?float $orderedQuantity = null;
    public ?float $filledQuantity = null;
    public ?float $averageExecutionPrice = null;
    public ?float $estimatedCommission = null;
    public ?float $estimatedFees = null;
    public ?float $bid = null;
    public ?float $ask = null;
    public ?float $lastprice = null;
    public ?string $currency = null;
    /**
     * @var LotDTO[]
     */
    public array $lots = [];
    public ?MFQuantityDTO $mfQuantity = null;
    public ?string $osiKey = null;
    public ?string $mfTransaction = null;
    public ?bool $reserveOrder = null;
    public ?float $reserveQuantity = null;

    /**
     * @param array $data
     * @return void
     */
    protected function fill(array $data): void
    {
        $product = $data['product'] ?? $data['Product'] ?? null;
        $lots = $data['lots'] ?? $data['Lots'] ?? null;
        $mfQuantity = $data['mfQuantity'] ?? $data['MFQuantity'] ?? null;

        unset($data['product'], $data['Product'], $data['lots'], $data['Lots'], $data['mfQuantity'], $data['MFQuantity']);

        parent::fill($data);

        if ($product !== null) {
            $this->product = new ProductDTO($product);
        }

        if ($lots !== null) {
            $lotItems = $lots['lot'] ?? $lots['Lot'] ?? $lots;
            $lotItems = $this->normalizeLotArray($lotItems);
            $this->lots = array_map(fn ($lot) => new LotDTO($lot), $lotItems);
        }

        if ($mfQuantity !== null) {
            $this->mfQuantity = new MFQuantityDTO($mfQuantity);
        }
    }

    /**
     * @param mixed $lotItems
     * @return array
     */
    private function normalizeLotArray(mixed $lotItems): array
    {
        if (!is_array($lotItems) || empty($lotItems)) {
            return [];
        }

        if (array_keys($lotItems) !== range(0, count($lotItems) - 1)) {
            return [$lotItems];
        }

        return $lotItems;
    }
}
