<?php

namespace KevinRider\LaravelEtrade\Dtos\LookupProduct;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class DataDTO extends BaseDTO
{
    public ?string $symbol = null;
    public ?string $description = null;
    public ?string $type = null;
}
