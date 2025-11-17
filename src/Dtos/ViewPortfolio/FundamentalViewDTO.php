<?php

namespace KevinRider\LaravelEtrade\Dtos\ViewPortfolio;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class FundamentalViewDTO extends BaseDTO
{
    public ?float $lastTrade = null;
    public ?int $lastTradeTime = null;
    public ?float $change = null;
    public ?float $changePct = null;
    public ?float $peRatio = null;
    public ?float $eps = null;
    public ?float $dividend = null;
    public ?float $divYield = null;
    public ?float $marketCap = null;
    public ?string $week52Range = null;
    public ?string $quoteStatus = null;
}
