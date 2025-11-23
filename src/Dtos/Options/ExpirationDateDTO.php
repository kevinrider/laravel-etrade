<?php

namespace KevinRider\LaravelEtrade\Dtos\Options;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class ExpirationDateDTO extends BaseDTO
{
    public ?int $year = null;
    public ?int $month = null;
    public ?int $day = null;
    public ?string $expiryType = null;
}
