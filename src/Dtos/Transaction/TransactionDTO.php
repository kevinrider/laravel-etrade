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
    public string $transactionType;
    public string $instType;
    public string $storeId;
    public CategoryDTO $category;
    public BrokerageDTO $brokerage;

    public function __construct(array $data)
    {
        if (isset($data['Category'])) {
            $this->category = new CategoryDTO($data['Category']);
            unset($data['Category']);
        }
        if (isset($data['category'])) {
            $this->category = new CategoryDTO($data['category']);
            unset($data['category']);
        }

        if (isset($data['Brokerage'])) {
            $this->brokerage = new BrokerageDTO($data['Brokerage']);
            unset($data['Brokerage']);
        }
        if (isset($data['brokerage'])) {
            $this->brokerage = new BrokerageDTO($data['brokerage']);
            unset($data['brokerage']);
        }
        parent::__construct($data);
    }
}
