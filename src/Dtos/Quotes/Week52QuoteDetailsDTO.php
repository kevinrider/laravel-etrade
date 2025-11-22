<?php

namespace KevinRider\LaravelEtrade\Dtos\Quotes;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class Week52QuoteDetailsDTO extends BaseDTO
{
    public ?string $companyName = null;
    public ?float $high52 = null;
    public ?float $lastTrade = null;
    public ?float $low52 = null;
    public ?float $perf12Months = null;
    public ?float $previousClose = null;
    public ?string $symbolDescription = null;
    public ?int $totalVolume = null;
}
