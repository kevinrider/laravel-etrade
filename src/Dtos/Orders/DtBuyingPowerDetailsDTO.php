<?php

namespace KevinRider\LaravelEtrade\Dtos\Orders;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class DtBuyingPowerDetailsDTO extends BaseDTO
{
    public ?OrderBuyPowerEffectDTO $nonMarginable = null;
    public ?OrderBuyPowerEffectDTO $marginable = null;

    /**
    * @param array $data
    * @return void
    */
    protected function fill(array $data): void
    {
        $nonMarginable = $data['nonMarginable'] ?? $data['NonMarginable'] ?? null;
        $marginable = $data['marginable'] ?? $data['Marginable'] ?? null;

        unset($data['nonMarginable'], $data['NonMarginable'], $data['marginable'], $data['Marginable']);

        parent::fill($data);

        if ($nonMarginable !== null) {
            $this->nonMarginable = new OrderBuyPowerEffectDTO($nonMarginable);
        }

        if ($marginable !== null) {
            $this->marginable = new OrderBuyPowerEffectDTO($marginable);
        }
    }
}
