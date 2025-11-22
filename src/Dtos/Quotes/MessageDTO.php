<?php

namespace KevinRider\LaravelEtrade\Dtos\Quotes;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class MessageDTO extends BaseDTO
{
    public ?string $description = null;
    public ?int $code = null;
    public ?string $type = null;
}
