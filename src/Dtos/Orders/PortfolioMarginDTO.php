<?php

namespace KevinRider\LaravelEtrade\Dtos\Orders;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class PortfolioMarginDTO extends BaseDTO
{
    public ?float $houseExcessEquityNew = null;
    public ?bool $pmEligible = null;
    public ?float $houseExcessEquityCurr = null;
    public ?float $houseExcessEquityChange = null;
}
