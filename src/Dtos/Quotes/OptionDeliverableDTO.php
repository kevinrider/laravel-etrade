<?php

namespace KevinRider\LaravelEtrade\Dtos\Quotes;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class OptionDeliverableDTO extends BaseDTO
{
    public ?string $rootSymbol = null;
    public ?string $deliverableSymbol = null;
    public ?string $deliverableTypeCode = null;
    public ?string $deliverableExchangeCode = null;
    public ?float $deliverableStrikePercent = null;
    public ?float $deliverableCILShares = null;
    public ?int $deliverableWholeShares = null;
}
