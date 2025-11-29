<?php

namespace KevinRider\LaravelEtrade\Dtos\Orders;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class PreviewIdDTO extends BaseDTO
{
    public ?int $previewId = null;
    public ?string $cashMargin = null;
}
