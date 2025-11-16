<?php

namespace KevinRider\LaravelEtrade\Dtos\AccountBalance;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class OpenCallsDTO extends BaseDTO
{
    public float $minEquityCall;
    public float $fedCall;
    public float $cashCall;
    public float $houseCall;
}
