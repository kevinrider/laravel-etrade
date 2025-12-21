<?php

namespace KevinRider\LaravelEtrade\Dtos\ListAccounts;

use Illuminate\Support\Carbon;
use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class AccountDTO extends BaseDTO
{
    public ?string $accountId = null;
    public ?string $accountIdKey = null;
    public ?string $accountMode = null;
    public ?string $accountDesc = null;
    public ?string $accountName = null;
    public ?string $accountType = null;
    public ?string $institutionType = null;
    public ?string $accountStatus = null;
    public ?Carbon $closedDate = null;
}
