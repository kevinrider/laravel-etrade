<?php

namespace KevinRider\LaravelEtrade\Dtos\Quotes;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class ProductDTO extends BaseDTO
{
    public ?string $symbol = null;
    public ?string $securityType = null;
    public ?string $securitySubType = null;
    public ?string $callPut = null;
    public ?int $expiryYear = null;
    public ?int $expiryMonth = null;
    public ?int $expiryDay = null;
    public ?float $strikePrice = null;
    public ?string $expiryType = null;
    public ?ProductIdDTO $productId = null;

    /**
     * @param array $data
     * @return void
     */
    protected function fill(array $data): void
    {
        $productId = $data['productId'] ?? $data['ProductId'] ?? null;
        unset($data['productId'], $data['ProductId']);

        parent::fill($data);

        if ($productId !== null) {
            $this->productId = new ProductIdDTO($productId);
        }
    }
}
