<?php

namespace KevinRider\LaravelEtrade\Dtos\Request;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class ListAlertDetailsRequestDTO extends BaseDTO
{
    public const array ALLOWED_QUERY_PARAMS = ['htmlTags'];

    public ?int $alertId = null;
    public ?bool $htmlTags = null;
}
