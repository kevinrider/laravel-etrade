<?php

namespace KevinRider\LaravelEtrade\Dtos\Response;

use Illuminate\Support\Carbon;
use KevinRider\LaravelEtrade\Dtos\BaseDTO;
use KevinRider\LaravelEtrade\Dtos\Orders\MessagesDTO;

class CancelOrderResponseDTO extends BaseDTO
{
    public ?string $accountId = null;
    public ?int $orderId = null;
    public ?Carbon $cancelTime = null;
    public ?MessagesDTO $messages = null;

    /**
     * @param string $json
     * @return static
     */
    public static function fromJson(string $json): static
    {
        $data = json_decode($json, true);

        return new static($data['CancelOrderResponse'] ?? $data);
    }

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
