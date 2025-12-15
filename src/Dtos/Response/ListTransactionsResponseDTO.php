<?php

namespace KevinRider\LaravelEtrade\Dtos\Response;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;
use KevinRider\LaravelEtrade\Dtos\Transaction\TransactionDTO;

class ListTransactionsResponseDTO extends BaseDTO
{
    /**
     * @var TransactionDTO[]
     */
    public array $transactions;
    public string $pageMarkers;
    public string $moreTransactions;
    public string $transactionCount;
    public string $totalCount;

    public function __construct(array $data)
    {
        if (isset($data['Transaction'])) {
            $this->transactions = array_map(fn ($transaction) => new TransactionDTO($transaction), $data['Transaction']);
            unset($data['Transaction']);
        }
        parent::__construct($data);
    }
}
