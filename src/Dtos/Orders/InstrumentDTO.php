<?php

namespace KevinRider\LaravelEtrade\Dtos\Orders;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;
use KevinRider\LaravelEtrade\Dtos\Shared\LotDTO;
use KevinRider\LaravelEtrade\Dtos\Shared\MFQuantityDTO;
use ReflectionClass;
use ReflectionProperty;

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
            $this->product = new ProductDTO(is_array($product) ? $product : $product->toArray());
        }

        if ($lots !== null) {
            $lotsArray = $lots['lot'] ?? $lots['Lot'] ?? $lots;
            $lotsArray = $this->normalizeArray($lotsArray);
            $this->lots = array_map(
                fn ($lot) => new LotDTO($lot),
                $lotsArray
            );
        }

        if ($mfQuantity !== null) {
            $this->mfQuantity = new MFQuantityDTO($mfQuantity);
        }
    }

    /**
     * @param mixed $items
     * @return array
     */
    private function normalizeArray(mixed $items): array
    {
        if (!is_array($items) || empty($items)) {
            return [];
        }

        if (array_keys($items) !== range(0, count($items) - 1)) {
            return [$items];
        }

        return $items;
    }

    public function toArray(): array
    {
        $reflection = new ReflectionClass($this);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        $data = [];
        foreach ($properties as $property) {
            $data[$property->getName() == 'product' ? 'Product' : $property->getName()] = $this->{$property->getName()};
        }

        return $data;
    }
}
