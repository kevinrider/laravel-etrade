<?php

namespace KevinRider\LaravelEtrade\Dtos\Response;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;
use KevinRider\LaravelEtrade\Dtos\Options\ExpirationDateDTO;

class OptionExpireDateResponseDTO extends BaseDTO
{
    /**
     * @var ExpirationDateDTO[]
     */
    public array $expirationDates = [];

    /**
     * @param array $data
     * @return void
     */
    protected function fill(array $data): void
    {
        $expirationDates = $data['expirationDates'] ?? $data['ExpirationDates'] ?? $data['ExpirationDate'] ?? $data['expirationDate'] ?? null;
        if ($expirationDates !== null) {
            $this->expirationDates = array_map(
                fn ($expirationDate) => new ExpirationDateDTO($expirationDate),
                $this->normalizeExpirationDatesArray($expirationDates)
            );

            unset($data['expirationDates'], $data['ExpirationDates'], $data['ExpirationDate'], $data['expirationDate']);
        }

        parent::fill($data);
    }

    /**
     * @param mixed $expirationDates
     * @return array
     */
    private function normalizeExpirationDatesArray(mixed $expirationDates): array
    {
        if (!is_array($expirationDates) || empty($expirationDates)) {
            return [];
        }

        if (array_keys($expirationDates) !== range(0, count($expirationDates) - 1)) {
            return [$expirationDates];
        }

        return $expirationDates;
    }
}
