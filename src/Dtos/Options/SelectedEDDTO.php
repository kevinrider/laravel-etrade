<?php

namespace KevinRider\LaravelEtrade\Dtos\Options;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class SelectedEDDTO extends BaseDTO
{
    public ?int $month = null;
    public ?int $year = null;
    public ?int $day = null;
}
