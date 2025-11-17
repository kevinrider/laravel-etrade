<?php

namespace KevinRider\LaravelEtrade\Dtos\ViewPortfolio;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class ProductDTO extends BaseDTO
{
    public string $symbol;
    public string $securityType;
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
        if (isset($data['productId'])) {
            $this->productId = new ProductIdDTO($data['productId']);
            unset($data['productId']);
        }

        parent::__construct($data);
    }
}
