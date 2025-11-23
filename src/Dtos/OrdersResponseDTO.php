<?php

namespace KevinRider\LaravelEtrade\Dtos;

use KevinRider\LaravelEtrade\Dtos\ListOrders\MessagesDTO;
use KevinRider\LaravelEtrade\Dtos\ListOrders\OrderDTO;

class OrdersResponseDTO extends BaseDTO
{
    public ?string $marker = null;
    public ?string $next = null;
    /**
     * @var OrderDTO[]
     */
    public array $order = [];
    public ?MessagesDTO $messages = null;

    /**
     * @param array $data
     * @return void
     */
    protected function fill(array $data): void
    {
        $orders = $data['order'] ?? $data['Order'] ?? null;
        if ($orders !== null) {
            $this->order = array_map(
                fn ($order) => new OrderDTO($order),
                $this->normalizeOrderArray($orders)
            );
            unset($data['order'], $data['Order']);
        }

        $messages = $data['messages'] ?? $data['Messages'] ?? null;
        if ($messages !== null) {
            $this->messages = new MessagesDTO($messages);
            unset($data['messages'], $data['Messages']);
        }

        parent::fill($data);
    }

    /**
     * @param mixed $orders
     * @return array
     */
    private function normalizeOrderArray(mixed $orders): array
    {
        if (!is_array($orders) || empty($orders)) {
            return [];
        }

        if (array_keys($orders) !== range(0, count($orders) - 1)) {
            return [$orders];
        }

        return $orders;
    }
}
