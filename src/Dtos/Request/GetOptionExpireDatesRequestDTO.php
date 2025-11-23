<?php

namespace KevinRider\LaravelEtrade\Dtos\Request;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class GetOptionExpireDatesRequestDTO extends BaseDTO
{
    public const array ALLOWED_QUERY_PARAMS = [
        'symbol',
        'expiryType',
    ];

    public ?string $symbol = null;
    public ?string $expiryType = null;
}
