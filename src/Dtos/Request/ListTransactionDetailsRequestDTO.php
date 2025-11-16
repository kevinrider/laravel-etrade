<?php

namespace KevinRider\LaravelEtrade\Dtos\Request;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class ListTransactionDetailsRequestDTO extends BaseDTO
{
    public const array ALLOWED_QUERY_PARAMS = ['storeId'];
    public string $accountIdKey;
    public string $transactionId;
    public string $storeId;
}
