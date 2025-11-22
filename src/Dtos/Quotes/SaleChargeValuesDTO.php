<?php

namespace KevinRider\LaravelEtrade\Dtos\Quotes;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class SaleChargeValuesDTO extends BaseDTO
{
    public ?string $lowhigh = null;
    public ?string $percent = null;
}
