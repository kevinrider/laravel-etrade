<?php

namespace KevinRider\LaravelEtrade\Dtos;

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
