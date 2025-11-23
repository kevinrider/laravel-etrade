<?php

namespace KevinRider\LaravelEtrade\Dtos\ListOrders;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class OrderDetailDTO extends BaseDTO
{
    public ?int $orderNumber = null;
    public ?string $accountId = null;
    public ?int $previewTime = null;
    public ?int $placedTime = null;
    public ?int $executedTime = null;
    public ?float $orderValue = null;
    public ?string $status = null;
    public ?string $orderType = null;
    public ?string $orderTerm = null;
    public ?string $priceType = null;
    public ?string $priceValue = null;
    public ?float $limitPrice = null;
    public ?float $stopPrice = null;
    public ?float $stopLimitPrice = null;
    public ?string $offsetType = null;
    public ?float $offsetValue = null;
    public ?string $marketSession = null;
    public ?string $routingDestination = null;
    public ?float $bracketedLimitPrice = null;
    public ?float $initialStopPrice = null;
    public ?bool $allOrNone = null;
    public ?int $previewId = null;
    /**
     * @var InstrumentDTO[]
     */
    public array $instrument = [];
    public ?MessagesDTO $messages = null;
    public ?float $investmentAmount = null;
    public ?string $positionQuantity = null;

    /**
     * @param array $data
     * @return void
     */
    protected function fill(array $data): void
    {
        $instruments = $data['instrument'] ?? $data['Instrument'] ?? null;
        $messages = $data['messages'] ?? $data['Messages'] ?? null;

        unset($data['instrument'], $data['Instrument'], $data['messages'], $data['Messages']);

        parent::fill($data);

        if ($instruments !== null) {
            $instruments = $this->normalizeInstrumentArray($instruments);
            $this->instrument = array_map(
                fn ($instrument) => new InstrumentDTO($instrument),
                $instruments
            );
        }

        if ($messages !== null) {
            $this->messages = new MessagesDTO($messages);
        }
    }

    /**
     * @param mixed $instruments
     * @return array
     */
    private function normalizeInstrumentArray(mixed $instruments): array
    {
        if (!is_array($instruments) || empty($instruments)) {
            return [];
        }

        if (array_keys($instruments) !== range(0, count($instruments) - 1)) {
            return [$instruments];
        }

        return $instruments;
    }
}
