<?php

namespace KevinRider\LaravelEtrade\Dtos\Request;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;
use KevinRider\LaravelEtrade\Dtos\Orders\OrderDetailDTO;

class PreviewOrderRequestDTO extends BaseDTO
{
    public const array REQUIRED_PROPERTIES = [
        'accountIdKey',
        'orderType',
        'order',
        'clientOrderId',
    ];

    public ?string $accountIdKey = null;
    public ?string $orderType = null;
    public ?string $clientOrderId = null;
    /**
     * @var array<int, OrderDetailDTO|array>
     */
    public array $order = [];

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

    /**
     * @param array<int, OrderDetailDTO|array> $orders
     * @return array
     */
    private function normalizeOrders(array $orders): array
    {
        return array_map(
            function ($order) {
                $orderArray = $this->dtoToArray($order);

                if (!array_key_exists('stopPrice', $orderArray)) {
                    $orderArray['stopPrice'] = '';
                }

                if (isset($orderArray['instrument'])) {
                    $orderArray['Instrument'] = array_map(
                        fn ($instrument) => $this->normalizeInstrument($instrument),
                        $orderArray['instrument']
                    );
                    unset($orderArray['instrument']);
                }

                return $orderArray;
            },
            $orders
        );
    }

    /**
     * @param mixed $instrument
     * @return array
     */
    private function normalizeInstrument(mixed $instrument): array
    {
        $instrumentArray = $this->dtoToArray($instrument);

        if (isset($instrumentArray['product'])) {
            $instrumentArray['Product'] = $this->dtoToArray($instrumentArray['product']);
            unset($instrumentArray['product']);
        }

        return $instrumentArray;
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
