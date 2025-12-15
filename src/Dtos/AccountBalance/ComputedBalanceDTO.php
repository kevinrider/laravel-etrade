<?php

namespace KevinRider\LaravelEtrade\Dtos\AccountBalance;

use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class ComputedBalanceDTO extends BaseDTO
{
    public ?float $cashAvailableForInvestment = null;
    public ?float $cashAvailableForWithdrawal = null;
    public ?float $totalAvailableForWithdrawal = null;
    public ?float $netCash = null;
    public ?float $cashBalance = null;
    public ?float $settledCashForInvestment = null;
    public ?float $unSettledCashForInvestment = null;
    public ?float $fundsWithheldFromPurchasePower = null;
    public ?float $fundsWithheldFromWithdrawal = null;
    public ?float $marginBuyingPower = null;
    public ?float $cashBuyingPower = null;
    public ?float $dtMarginBuyingPower = null;
    public ?float $dtCashBuyingPower = null;
    public ?float $marginBalance = null;
    public ?float $shortAdjustBalance = null;
    public ?float $regtEquity = null;
    public ?float $regtEquityPercent = null;
    public ?float $accountBalance = null;
    public ?OpenCallsDTO $openCalls = null;
    public ?RealTimeValuesDTO $realTimeValues = null;
    public ?PortfolioMarginDTO $portfolioMargin = null;

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
