<?php

namespace KevinRider\LaravelEtrade\Dtos\Transaction;

use Illuminate\Support\Carbon;
use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class TransactionDTO extends BaseDTO
{
    public ?string $transactionId = null;
    public ?string $accountId = null;
    public ?Carbon $transactionDate = null;
    public ?Carbon $postDate = null;
    public ?float $amount = null;
    public ?string $description = null;
    public ?string $transactionType = null;
    public ?string $instType = null;
    public ?string $storeId = null;
    public ?CategoryDTO $category = null;
    public ?BrokerageDTO $brokerage = null;

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
