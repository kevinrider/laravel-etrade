<?php

namespace KevinRider\LaravelEtrade\Dtos\ViewPortfolio;

use Illuminate\Support\Carbon;
use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class OptionsWatchViewDTO extends BaseDTO
{
    public ?string $baseSymbolAndPrice = null;
    public ?float $premium = null;
    public ?float $lastTrade = null;
    public ?float $bid = null;
    public ?float $ask = null;
    public ?string $quoteStatus = null;
    public ?Carbon $lastTradeTime = null;
}
