<?php

namespace KevinRider\LaravelEtrade\Dtos\AccountBalance;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class LendingDTO extends BaseDTO
{
    public float $currentBalance;
    public float $creditLine;
    public float $outstandingBalance;
    public float $minPaymentDue;
    public float $amountPastDue;
    public float $availableCredit;
    public float $ytdInterestPaid;
    public float $lastYtdInterestPaid;
    public int $paymentDueDate;
    public int $lastPaymentReceivedDate;
    public float $paymentReceivedMtd;
}
