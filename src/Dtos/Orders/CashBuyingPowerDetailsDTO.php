<?php

namespace KevinRider\LaravelEtrade\Dtos\Orders;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class CashBuyingPowerDetailsDTO extends BaseDTO
{
    public ?OrderBuyPowerEffectDTO $settled = null;
    public ?OrderBuyPowerEffectDTO $settledUnsettled = null;

    /**
    * @param array $data
    * @return void
    */
    protected function fill(array $data): void
    {
        $settled = $data['settled'] ?? $data['Settled'] ?? null;
        $settledUnsettled = $data['settledUnsettled'] ?? $data['SettledUnsettled'] ?? null;

        unset($data['settled'], $data['Settled'], $data['settledUnsettled'], $data['SettledUnsettled']);

        parent::fill($data);

        if ($settled !== null) {
            $this->settled = new OrderBuyPowerEffectDTO($settled);
        }

        if ($settledUnsettled !== null) {
            $this->settledUnsettled = new OrderBuyPowerEffectDTO($settledUnsettled);
        }
    }
}
