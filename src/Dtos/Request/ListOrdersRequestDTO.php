<?php

namespace KevinRider\LaravelEtrade\Dtos\Request;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class ListOrdersRequestDTO extends BaseDTO
{
    public const array ALLOWED_QUERY_PARAMS = [
        'marker',
        'count',
        'status',
        'fromDate',
        'toDate',
        'symbol',
        'securityType',
        'transactionType',
        'marketSession',
    ];

    public ?string $accountIdKey = null;
    public ?string $marker = null;
    public ?int $count = null;
    public ?string $status = null;
    public ?string $fromDate = null;
    public ?string $toDate = null;
    public ?string $symbol = null;
    public ?string $securityType = null;
    public ?string $transactionType = null;
    public ?string $marketSession = null;
    public int $callDepth = 10;
}
