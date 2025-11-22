<?php

namespace KevinRider\LaravelEtrade\Dtos\Quotes;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class NetAssetDTO extends BaseDTO
{
    public ?float $value = null;
    public ?int $asOfDate = null;
}
