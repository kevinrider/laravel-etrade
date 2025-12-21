<?php

namespace KevinRider\LaravelEtrade\Dtos\ViewPortfolio;

use Illuminate\Support\Carbon;
use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class PositionLotDTO extends BaseDTO
{
    public ?int $positionId = null;
    public ?int $positionLotId = null;
    public ?float $price = null;
    public ?int $termCode = null;
    public ?float $daysGain = null;
    public ?float $daysGainPct = null;
    public ?float $marketValue = null;
    public ?float $totalCost = null;
    public ?float $totalCostForGainPct = null;
    public ?float $totalGain = null;
    public ?int $lotSourceCode = null;
    public ?float $originalQty = null;
    public ?float $remainingQty = null;
    public ?float $availableQty = null;
    public ?int $orderNo = null;
    public ?int $legNo = null;
    public ?Carbon $acquiredDate = null;
    public ?int $locationCode = null;
    public ?float $exchangeRate = null;
    public ?string $settlementCurrency = null;
    public ?string $paymentCurrency = null;
    public ?float $adjPrice = null;
    public ?float $commPerShare = null;
    public ?float $feesPerShare = null;
    public ?float $premiumAdj = null;
    public ?int $shortType = null;
}
