<?php

namespace KevinRider\LaravelEtrade\Dtos\Options;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class OptionDetailsDTO extends BaseDTO
{
    public ?string $optionCategory = null;
    public ?string $optionRootSymbol = null;
    public ?int $timeStamp = null;
    public ?bool $adjustedFlag = null;
    public ?string $displaySymbol = null;
    public ?string $optionType = null;
    public ?float $strikePrice = null;
    public ?string $symbol = null;
    public ?float $bid = null;
    public ?float $ask = null;
    public ?int $bidSize = null;
    public ?int $askSize = null;
    public ?string $inTheMoney = null;
    public ?int $volume = null;
    public ?int $openInterest = null;
    public ?float $netChange = null;
    public ?float $lastPrice = null;
    public ?string $quoteDetail = null;
    public ?string $osiKey = null;
    public ?OptionGreeksDTO $optionGreek = null;

    /**
     * @param array $data
     * @return void
     */
    protected function fill(array $data): void
    {
        $optionGreeks = $data['optionGreek'] ?? $data['OptionGreeks'] ?? $data['optionGreeks'] ?? null;
        unset($data['optionGreek'], $data['OptionGreeks'], $data['optionGreeks']);

        parent::fill($data);

        if ($optionGreeks !== null) {
            $this->optionGreek = new OptionGreeksDTO($optionGreeks);
        }
    }
}
