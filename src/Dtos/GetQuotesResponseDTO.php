<?php

namespace KevinRider\LaravelEtrade\Dtos;

use KevinRider\LaravelEtrade\Dtos\Quotes\MessagesDTO;
use KevinRider\LaravelEtrade\Dtos\Quotes\QuoteDataDTO;

class GetQuotesResponseDTO extends BaseDTO
{
    /**
     * @var QuoteDataDTO[]
     */
    public array $quoteData = [];
    public ?MessagesDTO $messages = null;

    /**
     * @param array $data
     * @return void
     */
    protected function fill(array $data): void
    {
        $quoteData = $data['QuoteData'] ?? $data['quoteData'] ?? null;
        if ($quoteData !== null) {
            $this->quoteData = array_map(
                fn ($quoteDatum) => new QuoteDataDTO($quoteDatum),
                $this->normalizeQuoteDataArray($quoteData)
            );
            unset($data['QuoteData'], $data['quoteData']);
        }

        $messages = $data['Messages'] ?? $data['messages'] ?? null;
        if ($messages !== null) {
            $this->messages = new MessagesDTO($messages);
            unset($data['Messages'], $data['messages']);
        }

        parent::fill($data);
    }

    /**
     * @param mixed $quoteData
     * @return array
     */
    private function normalizeQuoteDataArray(mixed $quoteData): array
    {
        if (!is_array($quoteData) || empty($quoteData)) {
            return [];
        }

        if (array_keys($quoteData) !== range(0, count($quoteData) - 1)) {
            return [$quoteData];
        }

        return $quoteData;
    }
}
