<?php

namespace KevinRider\LaravelEtrade\Dtos\ViewPortfolio;

use Illuminate\Support\Carbon;
use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class CompleteViewDTO extends BaseDTO
{
    public ?bool $priceAdjustedFlag = null;
    public ?float $price = null;
    public ?float $adjPrice = null;
    public ?float $change = null;
    public ?float $changePct = null;
    public ?float $prevClose = null;
    public ?float $adjPrevClose = null;
    public ?float $volume = null;
    public ?float $lastTrade = null;
    public ?Carbon $lastTradeTime = null;
    public ?float $adjLastTrade = null;
    public ?string $symbolDescription = null;
    public ?float $perform1Month = null;
    public ?float $perform3Month = null;
    public ?float $perform6Month = null;
    public ?float $perform12Month = null;
    public ?int $prevDayVolume = null;
    public ?int $tenDayVolume = null;
    public ?float $beta = null;
    public ?float $sv10DaysAvg = null;
    public ?float $sv20DaysAvg = null;
    public ?float $sv1MonAvg = null;
    public ?float $sv2MonAvg = null;
    public ?float $sv3MonAvg = null;
    public ?float $sv4MonAvg = null;
    public ?float $sv6MonAvg = null;
    public ?float $week52High = null;
    public ?float $week52Low = null;
    public ?string $week52Range = null;
    public ?float $marketCap = null;
    public ?string $daysRange = null;
    public ?float $delta52WkHigh = null;
    public ?float $delta52WkLow = null;
    public ?string $currency = null;
    public ?string $exchange = null;
    public ?bool $marginable = null;
    public ?float $bid = null;
    public ?float $ask = null;
    public ?float $bidAskSpread = null;
    public ?int $bidSize = null;
    public ?int $askSize = null;
    public ?float $open = null;
    public ?float $delta = null;
    public ?float $gamma = null;
    public ?float $ivPct = null;
    public ?float $rho = null;
    public ?float $theta = null;
    public ?float $vega = null;
    public ?float $premium = null;
    public ?int $daysToExpiration = null;
    public ?float $intrinsicValue = null;
    public ?float $openInterest = null;
    public ?bool $optionsAdjustedFlag = null;
    public ?string $deliverablesStr = null;
    public ?float $optionMultiplier = null;
    public ?string $baseSymbolAndPrice = null;
    public ?float $estEarnings = null;
    public ?float $eps = null;
    public ?float $peRatio = null;
    public ?float $annualDividend = null;
    public ?float $dividend = null;
    public ?float $divYield = null;
    public ?Carbon $divPayDate = null;
    public ?Carbon $exDividendDate = null;
    public ?string $cusip = null;
    public ?string $quoteStatus = null;
}
