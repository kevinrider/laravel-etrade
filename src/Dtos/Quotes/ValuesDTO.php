<?php

namespace KevinRider\LaravelEtrade\Dtos\Quotes;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class ValuesDTO extends BaseDTO
{
    public ?string $low = null;
    public ?string $high = null;
    public ?string $percent = null;
}
