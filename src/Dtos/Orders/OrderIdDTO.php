<?php

namespace KevinRider\LaravelEtrade\Dtos\Orders;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class OrderIdDTO extends BaseDTO
{
    public ?int $orderId = null;
    public ?string $cashMargin = null;
}
