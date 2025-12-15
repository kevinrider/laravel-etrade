<?php

namespace KevinRider\LaravelEtrade\Dtos\Shared;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class OptionGreeksDTO extends BaseDTO
{
    public ?float $rho = null;
    public ?float $vega = null;
    public ?float $theta = null;
    public ?float $delta = null;
    public ?float $gamma = null;
    public ?float $iv = null;
    public ?bool $currentValue = null;
}
