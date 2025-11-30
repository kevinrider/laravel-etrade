<?php

namespace KevinRider\LaravelEtrade\Dtos\Request;

use KevinRider\LaravelEtrade\Dtos\Orders\PreviewIdDTO;

class PlaceOrderRequestDTO extends OrderRequestBaseDTO
{
    public const array REQUIRED_PROPERTIES = [
        'accountIdKey',
        'orderType',
        'order',
        'clientOrderId',
        'previewIds',
    ];

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
}
