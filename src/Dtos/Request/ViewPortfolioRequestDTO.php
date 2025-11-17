<?php

namespace KevinRider\LaravelEtrade\Dtos\Request;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class ViewPortfolioRequestDTO extends BaseDTO
{
    public const array ALLOWED_QUERY_PARAMS = [
        'count',
        'sortBy',
        'sortOrder',
        'pageNumber',
        'marketSession',
        'totalsRequired',
        'lotsRequired',
        'view',
    ];

    public string $accountIdKey;
    public ?int $count = null;
    public ?string $sortBy = null;
    public ?string $sortOrder = null;
    public ?int $pageNumber = null;
    public ?string $marketSession = null;
    public ?bool $totalsRequired = null;
    public ?bool $lotsRequired = null;
    public ?string $view = null;
}
