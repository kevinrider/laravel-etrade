<?php

namespace KevinRider\LaravelEtrade\Dtos\Transaction;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class BrokerageDTO extends BaseDTO
{
    public string $transactionType;
    public ProductDTO $product;
    public float $quantity;
    public float $price;
    public string $settlementCurrency;
    public string $paymentCurrency;
    public float $fee;
    public string $memo;

    public string $checkNo;
    public string $orderNo;

    /**
     * @param array $data
     */
    public function __construct(array $data)
    {
        if (isset($data['Product'])) {
            $this->product = new ProductDTO($data['Product']);
            unset($data['Product']);
        }
        if (isset($data['product'])) {
            $this->product = new ProductDTO($data['product']);
            unset($data['product']);
        }
        parent::__construct($data);
    }
}
