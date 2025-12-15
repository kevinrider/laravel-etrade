<?php

namespace KevinRider\LaravelEtrade\Dtos\Transaction;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;
use KevinRider\LaravelEtrade\Dtos\Shared\ProductIdDTO;

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

    public function __construct(array $data)
    {
        parent::__construct($data);

        if (isset($data['productId'])) {
            $this->productId = new ProductIdDTO($data['productId']);
        }
    }
}
