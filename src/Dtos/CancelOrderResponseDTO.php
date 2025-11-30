<?php

namespace KevinRider\LaravelEtrade\Dtos;

use KevinRider\LaravelEtrade\Dtos\Orders\MessagesDTO;

class CancelOrderResponseDTO extends BaseDTO
{
    public ?string $accountId = null;
    public ?int $orderId = null;
    public ?int $cancelTime = null;
    public ?MessagesDTO $messages = null;

    /**
     * @param array $data
     * @return void
     */
    protected function fill(array $data): void
    {
        $messages = $data['Messages'] ?? $data['messages'] ?? null;

        unset($data['Messages'], $data['messages']);

        parent::fill($data);

        if ($messages !== null) {
            $messageArray = $messages['Message'] ?? $messages['message'] ?? $messages;
            $this->messages = new MessagesDTO(['message' => $messageArray]);
        }
    }
}
