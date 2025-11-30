<?php

namespace KevinRider\LaravelEtrade\Dtos\Request;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class CancelOrderRequestDTO extends BaseDTO
{
    public ?string $accountIdKey = null;
    public ?int $orderId = null;

    /**
     * Build the API request payload.
     *
     * @return array<string, mixed>
     */
    public function toRequestBody(): array
    {
        return [
            'CancelOrderRequest' => [
                'orderId' => $this->orderId,
            ],
        ];
    }
}
