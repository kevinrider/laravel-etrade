<?php

namespace KevinRider\LaravelEtrade\Dtos\Request;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class AccountBalanceRequestDTO extends BaseDTO
{
    public const array ALLOWED_QUERY_PARAMS = ['instType', 'realTimeNAV', 'accountType'];
    public string $accountType;
    public string $instType = 'BROKERAGE';
    public string $realTimeNAV;
    public string $accountIdKey;
}
