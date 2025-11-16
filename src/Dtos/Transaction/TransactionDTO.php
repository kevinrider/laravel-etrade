<?php

namespace KevinRider\LaravelEtrade\Dtos\Transaction;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class TransactionDTO extends BaseDTO
{
    public string $transactionId;
    public string $accountId;
    public int $transactionDate;
    public int $postDate;
    public float $amount;
    public string $description;
    public CategoryDTO $category;
    public BrokerageDTO $brokerage;

    public function __construct(array $data)
    {
        parent::__construct($data);

        if (isset($data['category'])) {
            $this->category = new CategoryDTO($data['category']);
        }

        if (isset($data['brokerage'])) {
            $this->brokerage = new BrokerageDTO($data['brokerage']);
        }
    }
}
