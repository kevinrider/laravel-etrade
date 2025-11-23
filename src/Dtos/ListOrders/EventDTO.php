<?php

namespace KevinRider\LaravelEtrade\Dtos\ListOrders;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class EventDTO extends BaseDTO
{
    public ?string $name = null;
    public ?int $dateTime = null;
    public ?int $orderNumber = null;
    /**
     * @var InstrumentDTO[]
     */
    public array $instrument = [];

    /**
     * @param array $data
     * @return void
     */
    protected function fill(array $data): void
    {
        $instruments = $data['instrument'] ?? $data['Instrument'] ?? null;
        unset($data['instrument'], $data['Instrument']);

        parent::fill($data);

        if ($instruments !== null) {
            $instruments = $this->normalizeInstrumentArray($instruments);
            $this->instrument = array_map(
                fn ($instrument) => new InstrumentDTO($instrument),
                $instruments
            );
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
