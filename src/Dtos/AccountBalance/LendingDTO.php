<?php

namespace KevinRider\LaravelEtrade\Dtos\AccountBalance;

use Illuminate\Support\Carbon;
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
    public ?Carbon $paymentDueDate = null;
    public ?Carbon $lastPaymentReceivedDate = null;
    public ?float $paymentReceivedMtd = null;
}
