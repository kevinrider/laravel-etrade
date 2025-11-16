<?php

namespace KevinRider\LaravelEtrade\Dtos\AccountBalance;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class MarginDTO extends BaseDTO
{
    public float $dtCashOpenOrderReserve;
    public float $dtMarginOpenOrderReserve;
}
