<?php

namespace KevinRider\LaravelEtrade\Dtos\AccountBalance;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class RealTimeValuesDTO extends BaseDTO
{
    public float $totalAccountValue;
    public float $netMv;
    public float $netMvLong;
    public float $netMvShort;
    public float $totalLongValue;
}
