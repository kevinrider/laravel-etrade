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
        parent::__construct($data);

        if (isset($data['product'])) {
            $this->product = new ProductDTO($data['product']);
        }
    }
}
