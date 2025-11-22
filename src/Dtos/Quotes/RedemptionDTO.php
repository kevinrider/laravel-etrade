<?php

namespace KevinRider\LaravelEtrade\Dtos\Quotes;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class RedemptionDTO extends BaseDTO
{
    public ?string $minMonth = null;
    public ?string $feePercent = null;
    public ?string $isFrontEnd = null;
    /**
     * @var ValuesDTO[]
     */
    public array $frontEndValues = [];
    public ?string $redemptionDurationType = null;
    public ?string $isSales = null;
    public ?string $salesDurationType = null;
    /**
     * @var ValuesDTO[]
     */
    public array $salesValues = [];

    /**
     * @param array $data
     * @return void
     */
    protected function fill(array $data): void
    {
        parent::fill($data);

        $frontEndValues = $data['frontEndValues'] ?? $data['FrontEndValues'] ?? null;
        if ($frontEndValues !== null) {
            $this->frontEndValues = array_map(
                fn ($value) => new ValuesDTO($value),
                $this->normalizeValuesArray($frontEndValues)
            );
        }

        $salesValues = $data['salesValues'] ?? $data['SalesValues'] ?? null;
        if ($salesValues !== null) {
            $this->salesValues = array_map(
                fn ($value) => new ValuesDTO($value),
                $this->normalizeValuesArray($salesValues)
            );
        }
    }

    /**
     * @param mixed $values
     * @return array
     */
    private function normalizeValuesArray(mixed $values): array
    {
        if (is_array($values)) {
            $values = $values['Values'] ?? $values['values'] ?? $values;
        }

        if (!is_array($values) || empty($values)) {
            return [];
        }

        if (array_keys($values) !== range(0, count($values) - 1)) {
            return [$values];
        }

        return $values;
    }
}
