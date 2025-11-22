<?php

namespace KevinRider\LaravelEtrade\Dtos\Request;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class GetQuotesRequestDTO extends BaseDTO
{
    public const array ALLOWED_QUERY_PARAMS = [
        'detailFlag',
        'requireEarningsDate',
        'overrideSymbolCount',
        'skipMiniOptionsCheck',
    ];

    public array $symbols = [];
    public ?string $detailFlag = null;
    public ?bool $requireEarningsDate = null;
    public ?bool $overrideSymbolCount = null;
    public ?bool $skipMiniOptionsCheck = null;

    /**
     * @return string
     */
    public function getSymbols(): string
    {
        return implode(',', $this->symbols);
    }
}
