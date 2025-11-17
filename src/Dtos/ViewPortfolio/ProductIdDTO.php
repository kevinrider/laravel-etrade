<?php

namespace KevinRider\LaravelEtrade\Dtos\ViewPortfolio;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class ProductIdDTO extends BaseDTO
{
    public ?string $typeCode = null;
    public ?string $symbol = null;
}
