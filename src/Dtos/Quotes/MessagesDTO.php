<?php

namespace KevinRider\LaravelEtrade\Dtos\Quotes;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class MessagesDTO extends BaseDTO
{
    /**
     * @var MessageDTO[]
     */
    public array $messages = [];

    /**
     * @param array $data
     * @return void
     */
    protected function fill(array $data): void
    {
        $messages = $data['message'] ?? $data['Message'] ?? null;
        if ($messages !== null) {
            $this->messages = array_map(
                fn ($message) => new MessageDTO($message),
                $this->normalizeMessages($messages)
            );
        }
    }

    /**
     * @param mixed $messages
     * @return array
     */
    private function normalizeMessages(mixed $messages): array
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
