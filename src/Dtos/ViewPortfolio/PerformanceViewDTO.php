<?php

namespace KevinRider\LaravelEtrade\Dtos\ViewPortfolio;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class PerformanceViewDTO extends BaseDTO
{
    public ?float $change = null;
    public ?float $changePct = null;
    public ?float $lastTrade = null;
    public ?float $daysGain = null;
    public ?float $totalGain = null;
    public ?float $totalGainPct = null;
    public ?float $marketValue = null;
    public ?string $quoteStatus = null;
    public ?int $lastTradeTime = null;
}
