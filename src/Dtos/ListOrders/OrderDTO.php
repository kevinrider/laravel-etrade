<?php

namespace KevinRider\LaravelEtrade\Dtos\ListOrders;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class OrderDTO extends BaseDTO
{
    public ?int $orderId = null;
    public ?string $details = null;
    public ?string $orderType = null;
    public ?float $totalOrderValue = null;
    public ?float $totalCommission = null;
    /**
     * @var OrderDetailDTO[]
     */
    public array $orderDetail = [];
    /**
     * @var EventDTO[]
     */
    public array $events = [];

    /**
     * @param array $data
     * @return void
     */
    protected function fill(array $data): void
    {
        $orderDetail = $data['orderDetail'] ?? $data['OrderDetail'] ?? null;
        $events = $data['events'] ?? $data['Events'] ?? null;

        unset($data['orderDetail'], $data['OrderDetail'], $data['events'], $data['Events']);

        parent::fill($data);

        if ($orderDetail !== null) {
            $detailsArray = $this->normalizeArray($orderDetail);
            $this->orderDetail = array_map(
                fn ($detail) => new OrderDetailDTO($detail),
                $detailsArray
            );
        }

        if ($events !== null) {
            $eventArray = $events['event'] ?? $events['Event'] ?? $events;
            $eventArray = $this->normalizeArray($eventArray);
            $this->events = array_map(
                fn ($event) => new EventDTO($event),
                $eventArray
            );
        }
    }

    /**
     * @param mixed $items
     * @return array
     */
    private function normalizeArray(mixed $items): array
    {
        if (!is_array($items) || empty($items)) {
            return [];
        }

        if (array_keys($items) !== range(0, count($items) - 1)) {
            return [$items];
        }

        return $items;
    }
}
