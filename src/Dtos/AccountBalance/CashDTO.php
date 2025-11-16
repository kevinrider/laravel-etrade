<?php

namespace KevinRider\LaravelEtrade\Dtos\AccountBalance;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class CashDTO extends BaseDTO
{
    public float $fundsForOpenOrdersCash;
    public float $moneyMktBalance;
}
