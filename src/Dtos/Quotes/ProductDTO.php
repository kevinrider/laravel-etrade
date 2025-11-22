<?php

namespace KevinRider\LaravelEtrade\Dtos\Quotes;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class ProductDTO extends BaseDTO
{
    public const string SECURITY_TYPE_OPTION = 'OPTN';
    public ?string $symbol = null;
    public ?string $securityType = null;
    public ?string $securitySubType = null;
    public ?string $callPut = null;
    public ?int $expiryYear = null;
    public ?int $expiryMonth = null;
    public ?int $expiryDay = null;
    public ?float $strikePrice = null;
    public ?string $expiryType = null;
    public ?ProductIdDTO $productId = null;

    /**
     * @param array $data
     * @return void
     */
    protected function fill(array $data): void
    {
        $productId = $data['productId'] ?? $data['ProductId'] ?? null;
        unset($data['productId'], $data['ProductId']);

        parent::fill($data);

        if ($productId !== null) {
            $this->productId = new ProductIdDTO($productId);
        }
    }

    /**
     * @param ProductDTO $productDTO
     * @return string
     */
    public static function optionToSymbol(ProductDTO $productDTO): string
    {
        return implode(':', [
            $productDTO->symbol,
            $productDTO->expiryYear,
            sprintf('%02d', $productDTO->expiryMonth),
            sprintf('%02d', $productDTO->expiryDay),
            $productDTO->callPut,
            $productDTO->strikePrice
        ]);
    }
}
