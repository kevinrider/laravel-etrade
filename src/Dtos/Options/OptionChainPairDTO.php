<?php

namespace KevinRider\LaravelEtrade\Dtos\Options;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class OptionChainPairDTO extends BaseDTO
{
    public ?OptionDetailsDTO $call = null;
    public ?OptionDetailsDTO $put = null;
    public ?string $pairType = null;

    /**
     * @param array $data
     * @return void
     */
    protected function fill(array $data): void
    {
        $call = $data['optioncall'] ?? $data['OptionCall'] ?? $data['call'] ?? $data['Call'] ?? null;
        $put = $data['optionPut'] ?? $data['OptionPut'] ?? $data['put'] ?? $data['Put'] ?? null;

        unset($data['optioncall'], $data['OptionCall'], $data['call'], $data['Call']);
        unset($data['optionPut'], $data['OptionPut'], $data['put'], $data['Put']);

        parent::fill($data);

        if ($call !== null) {
            $this->call = new OptionDetailsDTO($call);
        }

        if ($put !== null) {
            $this->put = new OptionDetailsDTO($put);
        }
    }
}
