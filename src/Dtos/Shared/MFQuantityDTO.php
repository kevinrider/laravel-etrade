<?php

namespace KevinRider\LaravelEtrade\Dtos\Shared;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class MFQuantityDTO extends BaseDTO
{
    public ?float $cash = null;
    public ?float $margin = null;
    public ?string $cusip = null;
}
