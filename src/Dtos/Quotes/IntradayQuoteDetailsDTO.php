<?php

namespace KevinRider\LaravelEtrade\Dtos\Quotes;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class IntradayQuoteDetailsDTO extends BaseDTO
{
    public ?float $ask = null;
    public ?float $bid = null;
    public ?float $changeClose = null;
    public ?float $changeClosePercentage = null;
    public ?string $companyName = null;
    public ?float $high = null;
    public ?float $lastTrade = null;
    public ?float $low = null;
    public ?int $totalVolume = null;
}
