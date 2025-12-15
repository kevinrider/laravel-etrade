<?php

namespace KevinRider\LaravelEtrade\Dtos\Response;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;
use KevinRider\LaravelEtrade\Dtos\LookupProduct\DataDTO;

class LookupResponseDTO extends BaseDTO
{
    /**
     * @var DataDTO[]
     */
    public array $data = [];

    /**
     * @param array $data
     * @return void
     */
    protected function fill(array $data): void
    {
        $lookupData = $data['data'] ?? $data['Data'] ?? null;
        if ($lookupData !== null) {
            $this->data = array_map(
                fn ($datum) => new DataDTO($datum),
                $this->normalizeLookupDataArray($lookupData)
            );

            unset($data['data'], $data['Data']);
        }

        parent::fill($data);
    }

    /**
     * @param mixed $lookupData
     * @return array
     */
    private function normalizeLookupDataArray(mixed $lookupData): array
    {
        if (!is_array($lookupData) || empty($lookupData)) {
            return [];
        }

        if (array_keys($lookupData) !== range(0, count($lookupData) - 1)) {
            return [$lookupData];
        }

        return $lookupData;
    }
}
