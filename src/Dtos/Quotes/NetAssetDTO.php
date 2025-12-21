<?php

namespace KevinRider\LaravelEtrade\Dtos\Quotes;

use Illuminate\Support\Carbon;
use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class NetAssetDTO extends BaseDTO
{
    public ?float $value = null;
    public ?Carbon $asOfDate = null;
}
