<?php

namespace KevinRider\LaravelEtrade\Dtos\AccountBalance;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class PortfolioMarginDTO extends BaseDTO
{
    public float $dtCashOpenOrderReserve;
    public float $dtMarginOpenOrderReserve;
    public float $liquidatingEquity;
    public float $houseExcessEquity;
    public float $totalHouseRequirement;
    public float $excessEquityMinusRequirement;
    public float $totalMarginRqmts;
    public float $availExcessEquity;
    public float $excessEquity;
    public float $openOrderReserve;
    public float $fundsOnHold;
}
