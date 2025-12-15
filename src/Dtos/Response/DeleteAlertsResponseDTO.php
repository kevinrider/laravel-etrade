<?php

namespace KevinRider\LaravelEtrade\Dtos\Response;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;
use KevinRider\LaravelEtrade\Dtos\Alerts\FailedAlertsDTO;

class DeleteAlertsResponseDTO extends BaseDTO
{
    public ?string $result = null;
    public ?FailedAlertsDTO $failedAlerts = null;

    /**
     * @param array $data
     * @return void
     */
    protected function fill(array $data): void
    {
        $failedAlerts = $data['failedAlerts'] ?? null;
        if ($failedAlerts) {
            $this->failedAlerts = new FailedAlertsDTO($failedAlerts);
            unset($data['failedAlerts']);
        }

        parent::fill($data);
    }
}
