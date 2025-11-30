<?php

namespace KevinRider\LaravelEtrade\Dtos\Request;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class CancelOrderRequestDTO extends BaseDTO
{
    public ?string $accountIdKey = null;
    public ?int $orderId = null;

    /**
     * @return string
     */
    public function toXml(): string
    {
        $xml = new \SimpleXMLElement('<CancelOrderRequest/>');
        $xml->addChild('orderId', (string) $this->orderId);

        return $xml->asXML() ?: '';
    }
}
