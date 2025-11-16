<?php

namespace KevinRider\LaravelEtrade\Dtos;

use KevinRider\LaravelEtrade\Dtos\ListAccounts\AccountDTO;

class AccountListResponseDTO extends BaseDTO
{
    /**
     * @var AccountDTO[]
     */
    public array $accounts;

    /**
     * @param array $data
     * @return void
     */
    protected function fill(array $data): void
    {
        $this->accounts = [];
        if (isset($data['Accounts']['Account'])) {
            $accountsData = $data['Accounts']['Account'];
            // Handle case where there is only one account and simplexml doesn't create a nested array
            if (isset($accountsData['accountId'])) {
                $accountsData = [$accountsData];
            }
            $this->accounts = array_map(fn ($accountData) => new AccountDTO($accountData), $accountsData);
        }
    }
}
