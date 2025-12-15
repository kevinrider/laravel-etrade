<?php

namespace KevinRider\LaravelEtrade\Dtos\Transaction;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class BrokerageDTO extends BaseDTO
{
    public ?string $transactionType = null;
    public ?ProductDTO $product = null;
    public ?float $quantity = null;
    public ?float $price = null;
    public ?string $settlementCurrency = null;
    public ?string $paymentCurrency = null;
    public ?float $fee = null;
    public ?string $memo = null;

    public ?string $checkNo = null;
    public ?string $orderNo = null;

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
