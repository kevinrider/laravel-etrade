<?php

namespace KevinRider\LaravelEtrade\Dtos\Quotes;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;
use KevinRider\LaravelEtrade\Dtos\Shared\OptionGreeksDTO;

class OptionQuoteDetailsDTO extends BaseDTO
{
    public ?float $ask = null;
    public ?int $askSize = null;
    public ?float $bid = null;
    public ?int $bidSize = null;
    public ?string $companyName = null;
    public ?int $daysToExpiration = null;
    public ?float $lastTrade = null;
    public ?int $openInterest = null;
    public ?float $optionPreviousBidPrice = null;
    public ?float $optionPreviousAskPrice = null;
    public ?string $osiKey = null;
    public ?float $intrinsicValue = null;
    public ?float $timePremium = null;
    public ?float $optionMultiplier = null;
    public ?float $contractSize = null;
    public ?string $symbolDescription = null;
    public ?OptionGreeksDTO $optionGreeks = null;

    /**
     * @param array $data
     * @return void
     */
    protected function fill(array $data): void
    {
        $optionGreeks = $data['optionGreeks'] ?? $data['OptionGreeks'] ?? null;
        unset($data['optionGreeks'], $data['OptionGreeks']);

        parent::fill($data);

        if ($optionGreeks !== null) {
            $this->optionGreeks = new OptionGreeksDTO($optionGreeks);
        }
    }
}
