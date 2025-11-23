<?php

namespace KevinRider\LaravelEtrade\Dtos\Request;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class GetOptionChainsRequestDTO extends BaseDTO
{
    public const array ALLOWED_QUERY_PARAMS = [
        'symbol',
        'expiryYear',
        'expiryMonth',
        'expiryDay',
        'strikePriceNear',
        'noOfStrikes',
        'includeWeekly',
        'skipAdjusted',
        'optionCategory',
        'chainType',
        'priceType',
    ];

    public ?string $symbol = null;
    public ?int $expiryYear = null;
    public ?int $expiryMonth = null;
    public ?int $expiryDay = null;
    public ?float $strikePriceNear = null;
    public ?int $noOfStrikes = null;
    public ?bool $includeWeekly = null;
    public ?bool $skipAdjusted = null;
    public ?string $optionCategory = null;
    public ?string $chainType = null;
    public ?string $priceType = null;
}
