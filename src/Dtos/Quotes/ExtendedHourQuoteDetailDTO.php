<?php

namespace KevinRider\LaravelEtrade\Dtos\Quotes;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class ExtendedHourQuoteDetailDTO extends BaseDTO
{
    public ?float $lastPrice = null;
    public ?float $change = null;
    public ?float $percentChange = null;
    public ?float $bid = null;
    public ?int $bidSize = null;
    public ?float $ask = null;
    public ?int $askSize = null;
    public ?int $volume = null;
    public ?int $timeOfLastTrade = null;
    public ?string $timeZone = null;
    public ?string $quoteStatus = null;
}
