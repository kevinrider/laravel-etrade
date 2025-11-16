<?php

namespace KevinRider\LaravelEtrade\Dtos\Request;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class ListTransactionsRequestDTO extends BaseDTO
{
    public const array ALLOWED_QUERY_PARAMS = ['marker', 'count', 'startDate', 'endDate', 'sortOrder'];

    public string $accountIdKey;
    public string $startDate;
    public string $endDate;
    public string $sortOrder;

    public string $marker;
    public int $count;


}
