<?php

namespace KevinRider\LaravelEtrade\Dtos\Request;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class DeleteAlertsRequestDTO extends BaseDTO
{
    /**
     * @var array<int|string>
     */
    public array $alertIds = [];

    /**
     * @return string
     */
    public function getAlertIdsPathSegment(): string
    {
        if (empty($this->alertIds)) {
            return '';
        }
        return implode(',', array_map(fn ($id) => trim((string) $id), $this->alertIds));
    }
}
