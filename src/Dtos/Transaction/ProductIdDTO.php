<?php

namespace KevinRider\LaravelEtrade\Dtos\Transaction;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class ProductIdDTO extends BaseDTO
{
    public string $symbol;
    public string $typeCode;

}
