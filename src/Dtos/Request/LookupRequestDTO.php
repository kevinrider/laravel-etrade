<?php

namespace KevinRider\LaravelEtrade\Dtos\Request;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class LookupRequestDTO extends BaseDTO
{
    public ?string $search = null;
}
