<?php

namespace KevinRider\LaravelEtrade\Dtos;

use KevinRider\LaravelEtrade\Dtos\Alerts\FailedAlerts;

class DeleteAlertsResponseDTO extends BaseDTO
{
    public ?string $result = null;
    public ?FailedAlerts $failedAlerts = null;

    /**
     * @param array $data
     * @return void
     */
    protected function fill(array $data): void
    {
        $failedAlerts = $data['failedAlerts'] ?? null;
        if ($failedAlerts) {
            $this->failedAlerts = new FailedAlerts($failedAlerts);
            unset($data['failedAlerts']);
        }

        parent::fill($data);
    }
}
