<?php

namespace KevinRider\LaravelEtrade\Dtos\ListOrders;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class LotDTO extends BaseDTO
{
    public ?int $id = null;
    public ?float $size = null;
}
