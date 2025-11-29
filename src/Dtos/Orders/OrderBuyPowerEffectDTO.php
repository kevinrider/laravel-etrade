<?php

namespace KevinRider\LaravelEtrade\Dtos\Orders;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class OrderBuyPowerEffectDTO extends BaseDTO
{
    public ?float $currentBp = null;
    public ?float $currentOor = null;
    public ?float $currentNetBp = null;
    public ?float $currentOrderImpact = null;
    public ?float $netBp = null;
}
