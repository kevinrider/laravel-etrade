<?php

namespace KevinRider\LaravelEtrade\Dtos\AccountBalance;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class LendingDTO extends BaseDTO
{
    public ?float $currentBalance = null;
    public ?float $creditLine = null;
    public ?float $outstandingBalance = null;
    public ?float $minPaymentDue = null;
    public ?float $amountPastDue = null;
    public ?float $availableCredit = null;
    public ?float $ytdInterestPaid = null;
    public ?float $lastYtdInterestPaid = null;
    public ?int $paymentDueDate = null;
    public ?int $lastPaymentReceivedDate = null;
    public ?float $paymentReceivedMtd = null;
}
