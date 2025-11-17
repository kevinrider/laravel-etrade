<?php

namespace KevinRider\LaravelEtrade\Dtos\ViewPortfolio;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class PositionDTO extends BaseDTO
{
    public ?int $positionId = null;
    public ?string $accountId = null;
    public ?ProductDTO $product = null;
    public ?string $osiKey = null;
    public ?string $symbolDescription = null;
    public ?int $dateAcquired = null;
    public ?float $pricePaid = null;
    public ?float $price = null;
    public ?float $commissions = null;
    public ?float $otherFees = null;
    public ?float $quantity = null;
    public ?string $positionIndicator = null;
    public ?string $positionType = null;
    public ?float $change = null;
    public ?float $changePct = null;
    public ?float $daysGain = null;
    public ?float $daysGainPct = null;
    public ?float $marketValue = null;
    public ?float $totalCost = null;
    public ?float $totalGain = null;
    public ?float $totalGainPct = null;
    public ?float $pctOfPortfolio = null;
    public ?float $costPerShare = null;
    public ?float $todayCommissions = null;
    public ?float $todayFees = null;
    public ?float $todayPricePaid = null;
    public ?float $todayQuantity = null;
    public ?string $quoteStatus = null;
    public ?int $dateTimeUTC = null;
    public ?float $adjPrevClose = null;
    public ?PerformanceViewDTO $performance = null;
    public ?FundamentalViewDTO $fundamental = null;
    public ?OptionsWatchViewDTO $optionsWatch = null;
    public ?QuickViewDTO $quick = null;
    public ?CompleteViewDTO $complete = null;
    public ?string $lotsDetails = null;
    public ?string $quoteDetails = null;

    /**
     * @var PositionLotDTO[]
     */
    public array $positionLots = [];

    /**
     * @param array $data
     * @return void
     */
    protected function fill(array $data): void
    {
        if (isset($data['Product'])) {
            $this->product = new ProductDTO($data['Product']);
            unset($data['Product']);
        }

        if (isset($data['Performance'])) {
            $this->performance = new PerformanceViewDTO($data['Performance']);
            unset($data['Performance']);
        } elseif (isset($data['performance'])) {
            $this->performance = new PerformanceViewDTO($data['performance']);
            unset($data['performance']);
        }

        if (isset($data['Fundamental'])) {
            $this->fundamental = new FundamentalViewDTO($data['Fundamental']);
            unset($data['Fundamental']);
        } elseif (isset($data['fundamental'])) {
            $this->fundamental = new FundamentalViewDTO($data['fundamental']);
            unset($data['fundamental']);
        }

        if (isset($data['OptionsWatch'])) {
            $this->optionsWatch = new OptionsWatchViewDTO($data['OptionsWatch']);
            unset($data['OptionsWatch']);
        } elseif (isset($data['optionsWatch'])) {
            $this->optionsWatch = new OptionsWatchViewDTO($data['optionsWatch']);
            unset($data['optionsWatch']);
        }

        if (isset($data['Quick'])) {
            $this->quick = new QuickViewDTO($data['Quick']);
            unset($data['Quick']);
        } elseif (isset($data['quick'])) {
            $this->quick = new QuickViewDTO($data['quick']);
            unset($data['quick']);
        }

        if (isset($data['Complete'])) {
            $this->complete = new CompleteViewDTO($data['Complete']);
            unset($data['Complete']);
        } elseif (isset($data['complete'])) {
            $this->complete = new CompleteViewDTO($data['complete']);
            unset($data['complete']);
        }

        $positionLotsData = $data['positionLot'] ?? $data['PositionLot'] ?? null;
        if ($positionLotsData) {
            if (isset($positionLotsData['positionLotId'])) {
                $positionLotsData = [$positionLotsData];
            }
            $this->positionLots = array_map(fn ($lot) => new PositionLotDTO($lot), $positionLotsData);
            unset($data['positionLot'], $data['PositionLot']);
        }

        if (isset($data['quotestatus']) && !isset($data['quoteStatus'])) {
            $data['quoteStatus'] = $data['quotestatus'];
            unset($data['quotestatus']);
        }

        parent::fill($data);
    }
}
