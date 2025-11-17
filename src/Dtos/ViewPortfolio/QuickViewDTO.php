<?php

namespace KevinRider\LaravelEtrade\Dtos\ViewPortfolio;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class QuickViewDTO extends BaseDTO
{
    public ?float $lastTrade = null;
    public ?int $lastTradeTime = null;
    public ?float $change = null;
    public ?float $changePct = null;
    public ?int $volume = null;
    public ?string $quoteStatus = null;
    public ?float $sevenDayCurrentYield = null;
    public ?float $annualTotalReturn = null;
    public ?float $weightedAverageMaturity = null;
}
