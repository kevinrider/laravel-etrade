<?php

namespace KevinRider\LaravelEtrade\Dtos\AccountBalance;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class RealTimeValuesDTO extends BaseDTO
{
    public ?float $totalAccountValue = null;
    public ?float $netMv = null;
    public ?float $netMvLong = null;
    public ?float $netMvShort = null;
    public ?float $totalLongValue = null;
}
