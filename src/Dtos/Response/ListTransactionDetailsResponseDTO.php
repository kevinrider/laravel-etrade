<?php

namespace KevinRider\LaravelEtrade\Dtos\Response;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;
use KevinRider\LaravelEtrade\Dtos\Transaction\TransactionDTO;

class ListTransactionDetailsResponseDTO extends BaseDTO
{
    public TransactionDTO $transaction;

    public function __construct(array $data)
    {
        $this->transaction = new TransactionDTO($data);
        parent::__construct($data);
    }
}
