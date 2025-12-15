<?php

namespace KevinRider\LaravelEtrade\Dtos\AccountBalance;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class PortfolioMarginDTO extends BaseDTO
{
    public ?float $dtCashOpenOrderReserve = null;
    public ?float $dtMarginOpenOrderReserve = null;
    public ?float $liquidatingEquity = null;
    public ?float $houseExcessEquity = null;
    public ?float $totalHouseRequirement = null;
    public ?float $excessEquityMinusRequirement = null;
    public ?float $totalMarginRqmts = null;
    public ?float $availExcessEquity = null;
    public ?float $excessEquity = null;
    public ?float $openOrderReserve = null;
    public ?float $fundsOnHold = null;
}
