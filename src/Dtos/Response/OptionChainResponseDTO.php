<?php

namespace KevinRider\LaravelEtrade\Dtos\Response;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;
use KevinRider\LaravelEtrade\Dtos\Options\OptionChainPairDTO;
use KevinRider\LaravelEtrade\Dtos\Options\SelectedEDDTO;

class OptionChainResponseDTO extends BaseDTO
{
    /**
     * @var OptionChainPairDTO[]
     */
    public array $optionPairs = [];
    public ?int $timeStamp = null;
    public ?string $quoteType = null;
    public ?float $nearPrice = null;
    public ?SelectedEDDTO $selected = null;

    /**
     * @param array $data
     * @return void
     */
    protected function fill(array $data): void
    {
        $optionPairs = $data['optionPairs'] ?? $data['OptionPairs'] ?? $data['OptionPair'] ?? $data['optionPair'] ?? null;
        if ($optionPairs !== null) {
            $this->optionPairs = array_map(
                fn ($pair) => new OptionChainPairDTO($pair),
                $this->normalizeOptionPairsArray($optionPairs)
            );

            unset($data['optionPairs'], $data['OptionPairs'], $data['OptionPair'], $data['optionPair']);
        }

        $selected = $data['SelectedED'] ?? $data['selected'] ?? null;
        if ($selected !== null) {
            $this->selected = new SelectedEDDTO($selected);
            unset($data['SelectedED'], $data['selected']);
        }

        parent::fill($data);
    }

    /**
     * @param mixed $optionPairs
     * @return array
     */
    private function normalizeOptionPairsArray(mixed $optionPairs): array
    {
        if (!is_array($optionPairs) || empty($optionPairs)) {
            return [];
        }

        if (array_keys($optionPairs) !== range(0, count($optionPairs) - 1)) {
            return [$optionPairs];
        }

        return $optionPairs;
    }
}
