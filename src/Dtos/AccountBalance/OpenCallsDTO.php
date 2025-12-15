<?php

namespace KevinRider\LaravelEtrade\Dtos\AccountBalance;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class OpenCallsDTO extends BaseDTO
{
    public ?float $minEquityCall = null;
    public ?float $fedCall = null;
    public ?float $cashCall = null;
    public ?float $houseCall = null;
}
