<?php

namespace KevinRider\LaravelEtrade\Dtos;

use KevinRider\LaravelEtrade\Dtos\Transaction\TransactionDTO;

class ListTransactionsResponseDTO extends BaseDTO
{
    /**
     * @var TransactionDTO[]
     */
    public array $transactions;

    public function __construct(array $data)
    {
        parent::__construct($data);

        if (isset($data['Transaction'])) {
            $this->transactions = array_map(fn ($transaction) => new TransactionDTO($transaction), $data['Transaction']);
        }
    }
}
