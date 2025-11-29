<?php

namespace KevinRider\LaravelEtrade\Dtos\Orders;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class MessagesDTO extends BaseDTO
{
    /**
     * @var MessageDTO[]
     */
    public array $message = [];

    /**
     * @param array $data
     * @return void
     */
    protected function fill(array $data): void
    {
        $messages = $data['message'] ?? $data['Message'] ?? $data;
        $messages = $this->normalizeArray($messages);

        $this->message = array_map(
            fn ($message) => new MessageDTO($message),
            $messages
        );
    }

    /**
     * @param mixed $messages
     * @return array
     */
    private function normalizeArray(mixed $messages): array
    {
        if (!is_array($messages) || empty($messages)) {
            return [];
        }

        if (array_keys($messages) !== range(0, count($messages) - 1)) {
            return [$messages];
        }

        return $messages;
    }
}
