<?php

namespace KevinRider\LaravelEtrade\Dtos\Transaction;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class ProductDTO extends BaseDTO
{
    public string $symbol;
    public string $securityType;
    public string $securitySubType;
    public string $callPut;
    public int $expiryYear;
    public int $expiryMonth;
    public int $expiryDay;
    public float $strikePrice;
    public string $expiryType;
    public ProductIdDTO $productId;

    public function __construct(array $data)
    {
        parent::__construct($data);

        if (isset($data['productId'])) {
            $this->productId = new ProductIdDTO($data['productId']);
        }
    }
}
