<?php

namespace KevinRider\LaravelEtrade\Dtos\Quotes;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class FundamentalQuoteDetailsDTO extends BaseDTO
{
    public ?string $companyName = null;
    public ?float $eps = null;
    public ?float $estEarnings = null;
    public ?float $high52 = null;
    public ?float $lastTrade = null;
    public ?float $low52 = null;
    public ?string $symbolDescription = null;
}
