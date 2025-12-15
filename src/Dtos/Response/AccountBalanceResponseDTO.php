<?php

namespace KevinRider\LaravelEtrade\Dtos\Response;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;
use KevinRider\LaravelEtrade\Dtos\AccountBalance\CashDTO;
use KevinRider\LaravelEtrade\Dtos\AccountBalance\ComputedBalanceDTO;
use KevinRider\LaravelEtrade\Dtos\AccountBalance\LendingDTO;
use KevinRider\LaravelEtrade\Dtos\AccountBalance\MarginDTO;

class AccountBalanceResponseDTO extends BaseDTO
{
    public string $accountId;
    public string $accountType;
    public string $institutionType;
    public string $asOfDate;
    public string $optionLevel;
    public string $accountDescription;
    public string $quoteMode;
    public string $dayTraderStatus;
    public string $accountMode;
    public CashDTO $cash;
    public MarginDTO $margin;
    public LendingDTO $lending;
    public ComputedBalanceDTO $computedBalance;

    /**
     * @param array $data
     * @return void
     */
    protected function fill(array $data): void
    {
        parent::fill($data);

        if (isset($data['Computed'])) {
            $this->computedBalance = new ComputedBalanceDTO($data['Computed']);
        }
        if (isset($data['Cash'])) {
            $this->cash = new CashDTO($data['Cash']);
        }
        if (isset($data['Margin'])) {
            $this->margin = new MarginDTO($data['Margin']);
        }
        if (isset($data['Lending'])) {
            $this->lending = new LendingDTO($data['Lending']);
        }
    }
}
