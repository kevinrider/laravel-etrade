<?php

namespace KevinRider\LaravelEtrade\Dtos\Request;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;
use KevinRider\LaravelEtrade\Dtos\Orders\OrderDetailDTO;
use KevinRider\LaravelEtrade\Dtos\Orders\PreviewIdDTO;

class PlaceOrderRequestDTO extends BaseDTO
{
    public const array REQUIRED_PROPERTIES = [
        'accountIdKey',
        'orderType',
        'order',
        'clientOrderId',
        'previewIds',
    ];

    public ?string $accountIdKey = null;
    public ?string $orderType = null;
    public ?string $clientOrderId = null;
    /**
     * @var array<int, OrderDetailDTO|array>
     */
    public array $order = [];
    /**
     * @var array<int, PreviewIdDTO|array|int>
     */
    public array $previewIds = [];

    /**
     * Build the API request payload.
     *
     * @return array
     */
    public function toRequestBody(): array
    {
        $payload = $this->filterNull([
            'orderType' => $this->orderType,
            'clientOrderId' => $this->clientOrderId,
            'Order' => $this->normalizeOrders($this->order),
            'PreviewIds' => $this->normalizePreviewIds($this->previewIds),
        ]);

        return ['PlaceOrderRequest' => $payload];
    }

    /**
     * @param array<int, OrderDetailDTO|array> $orders
     * @return array
     */
    private function normalizeOrders(array $orders): array
    {
        return array_map(
            fn ($order) => $order instanceof OrderDetailDTO
                ? $this->dtoToArray($order)
                : $this->dtoToArray($order),
            $orders
        );
    }

    /**
     * @param array<int, PreviewIdDTO|array|int> $previewIds
     * @return array
     */
    private function normalizePreviewIds(array $previewIds): array
    {
        return array_map(function ($previewId) {
            if ($previewId instanceof PreviewIdDTO) {
                return $this->dtoToArray($previewId);
            }

            if (is_array($previewId)) {
                return $this->dtoToArray($previewId);
            }

            return ['previewId' => $previewId];
        }, $previewIds);
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function dtoToArray(mixed $value): mixed
    {
        if ($value instanceof BaseDTO) {
            $value = $value->toArray();
        }

        if (is_array($value)) {
            $value = array_map(fn ($item) => $this->dtoToArray($item), $value);
            return $this->filterNull($value);
        }

        return $value;
    }

    /**
     * @param array $payload
     * @return array
     */
    private function filterNull(array $payload): array
    {
        return array_filter(
            $payload,
            fn ($value) => $value !== null && !(is_array($value) && count($value) === 0)
        );
    }
}
