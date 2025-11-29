<?php

namespace KevinRider\LaravelEtrade\Dtos\Orders;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class DisclosureDTO extends BaseDTO
{
    public ?bool $ehDisclosureFlag = null;
    public ?bool $ahDisclosureFlag = null;
    public ?bool $conditionalDisclosureFlag = null;
    public ?bool $aoDisclosureFlag = null;
    public ?bool $mfFLConsent = null;
    public ?bool $mfEOConsent = null;
}
