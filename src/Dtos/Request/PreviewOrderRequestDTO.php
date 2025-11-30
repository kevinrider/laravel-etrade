<?php

namespace KevinRider\LaravelEtrade\Dtos\Request;

class PreviewOrderRequestDTO extends OrderRequestBaseDTO
{
    public const array REQUIRED_PROPERTIES = [
        'accountIdKey',
        'orderType',
        'order',
        'clientOrderId',
    ];

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
        ]);

        return ['PreviewOrderRequest' => $payload];
    }
}
