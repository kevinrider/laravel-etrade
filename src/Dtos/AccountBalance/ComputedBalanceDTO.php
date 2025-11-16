<?php

namespace KevinRider\LaravelEtrade\Dtos\AccountBalance;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class ComputedBalanceDTO extends BaseDTO
{
    public float $cashAvailableForInvestment;
    public float $cashAvailableForWithdrawal;
    public float $totalAvailableForWithdrawal;
    public float $netCash;
    public float $cashBalance;
    public float $settledCashForInvestment;
    public float $unSettledCashForInvestment;
    public float $fundsWithheldFromPurchasePower;
    public float $fundsWithheldFromWithdrawal;
    public float $marginBuyingPower;
    public float $cashBuyingPower;
    public float $dtMarginBuyingPower;
    public float $dtCashBuyingPower;
    public float $marginBalance;
    public float $shortAdjustBalance;
    public float $regtEquity;
    public float $regtEquityPercent;
    public float $accountBalance;
    public OpenCallsDTO $openCalls;
    public RealTimeValuesDTO $realTimeValues;
    public PortfolioMarginDTO $portfolioMargin;

    /**
     * @param array $data
     * @return void
     */
    protected function fill(array $data): void
    {
        parent::fill($data);

        if (isset($data['OpenCalls'])) {
            $this->openCalls = new OpenCallsDTO($data['OpenCalls']);
        }
        if (isset($data['RealTimeValues'])) {
            $this->realTimeValues = new RealTimeValuesDTO($data['RealTimeValues']);
        }
        if (isset($data['PortfolioMargin'])) {
            $this->portfolioMargin = new PortfolioMarginDTO($data['PortfolioMargin']);
        }
    }
}
