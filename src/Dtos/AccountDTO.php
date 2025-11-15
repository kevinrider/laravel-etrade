<?php

namespace KevinRider\LaravelEtrade\Dtos;

class AccountDTO extends BaseDTO
{
    public string $accountId;
    public string $accountIdKey;
    public string $accountMode;
    public string $accountDesc;
    public string $accountName;
    public string $accountType;
    public string $institutionType;
    public string $accountStatus;
    public int $closedDate;
}
