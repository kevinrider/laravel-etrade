<?php

namespace KevinRider\LaravelEtrade\Dtos;

use KevinRider\LaravelEtrade\Transaction\Dtos\TransactionDTO;

class ListTransactionDetailsResponseDTO extends BaseDTO
{
    public TransactionDTO $transaction;

    public function __construct(array $data)
    {
        parent::__construct($data);

        if (isset($data['Transaction'])) {
            $this->transaction = new TransactionDTO($data['Transaction']);
        }
    }
}
