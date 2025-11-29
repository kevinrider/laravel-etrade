<?php

namespace KevinRider\LaravelEtrade\Dtos\Orders;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class ProductIdDTO extends BaseDTO
{
    public ?string $symbol = null;
    public ?string $typeCode = null;
}
