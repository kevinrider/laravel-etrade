<?php

namespace KevinRider\LaravelEtrade\Dtos\Alerts;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class FailedAlerts extends BaseDTO
{
    /**
     * @var int[]
     */
    public array $alertId = [];

    /**
     * @param array $data
     * @return void
     */
    protected function fill(array $data): void
    {
        $alertIds = $data['alertId'] ?? null;
        if ($alertIds === null) {
            return;
        }

        if (!is_array($alertIds)) {
            $alertIds = [$alertIds];
        }

        $this->alertId = array_map(
            fn ($id) => is_numeric($id) ? (int) $id : $id,
            $alertIds
        );
    }
}
