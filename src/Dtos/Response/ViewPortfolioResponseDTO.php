<?php

namespace KevinRider\LaravelEtrade\Dtos\Response;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;
use KevinRider\LaravelEtrade\Dtos\ViewPortfolio\PositionDTO;

class ViewPortfolioResponseDTO extends BaseDTO
{
    public ?int $accountId = null;
    public ?string $next = null;
    public ?int $totalPages = null;
    public ?int $nextPageNo = null;
    /**
     * @var PositionDTO[]
     */
    public array $positions = [];

    /**
     * @param array $data
     * @return void
     */
    protected function fill(array $data): void
    {
        $data = $data['AccountPortfolio'] ?? $data['accountPortfolio'] ?? null;
        if ($data) {
            $positions = $data['Position'] ?? $data['position'] ?? null;
            $positions = $this->isMultiDimensional($positions) ? $positions : [$positions];
            $this->positions = array_map(fn ($accountPortfolio) => new PositionDTO($accountPortfolio), $positions);
            unset($data['Position'], $data['position']);
        }

        parent::fill($data);
    }

    /**
     * @param array $array
     * @return bool
     */
    protected function isMultiDimensional(array $array): bool
    {
        foreach ($array as $element) {
            if (!is_array($element)) {
                return false;
            }
        }
        return true;
    }
}
