<?php

namespace KevinRider\LaravelEtrade\Dtos;

use KevinRider\LaravelEtrade\Dtos\Alerts\AlertDTO;

class ListAlertsResponseDTO extends BaseDTO
{
    public ?int $totalAlerts = null;
    /**
     * @var AlertDTO[]
     */
    public array $alerts = [];

    /**
     * @param array $data
     * @return void
     */
    protected function fill(array $data): void
    {
        $alerts = $data['Alert'] ?? $data['alerts'] ?? null;
        if ($alerts !== null) {
            $this->alerts = array_map(
                fn ($alertData) => new AlertDTO($alertData),
                $this->normalizeAlertArray($alerts)
            );
            unset($data['Alert'], $data['alerts']);
        }

        parent::fill($data);
    }

    /**
     * @param mixed $alertsData
     * @return array
     */
    private function normalizeAlertArray(mixed $alertsData): array
    {
        if (!is_array($alertsData) || empty($alertsData)) {
            return [];
        }

        if ($this->isAssociativeArray($alertsData)) {
            return [$alertsData];
        }

        return $alertsData;
    }

    /**
     * @param array $array
     * @return bool
     */
    private function isAssociativeArray(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }
}
