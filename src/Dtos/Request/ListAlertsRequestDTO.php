<?php

namespace KevinRider\LaravelEtrade\Dtos\Request;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class ListAlertsRequestDTO extends BaseDTO
{
    public const array ALLOWED_QUERY_PARAMS = [
        'count',
        'category',
        'status',
        'direction',
        'search',
    ];

    public ?int $count = null;
    public ?string $category = null;
    public ?string $status = null;
    public ?string $direction = null;
    public ?string $search = null;
}
