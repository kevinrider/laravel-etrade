<?php

namespace KevinRider\LaravelEtrade\Dtos\Quotes;

use Illuminate\Support\Carbon;
use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class MutualFundDTO extends BaseDTO
{
    public ?string $symbolDescription = null;
    public ?string $cusip = null;
    public ?float $changeClose = null;
    public ?float $previousClose = null;
    public ?string $transactionFee = null;
    public ?string $earlyRedemptionFee = null;
    public ?string $availability = null;
    public ?float $initialInvestment = null;
    public ?float $subsequentInvestment = null;
    public ?string $fundFamily = null;
    public ?string $fundName = null;
    public ?float $changeClosePercentage = null;
    public ?Carbon $timeOfLastTrade = null;
    public ?float $netAssetValue = null;
    public ?float $publicOfferPrice = null;
    public ?float $netExpenseRatio = null;
    public ?float $grossExpenseRatio = null;
    public ?Carbon $orderCutoffTime = null;
    public ?string $salesCharge = null;
    public ?float $initialIraInvestment = null;
    public ?float $subsequentIraInvestment = null;
    public ?NetAssetDTO $netAssets = null;
    public ?Carbon $fundInceptionDate = null;
    public ?float $averageAnnualReturns = null;
    public ?float $sevenDayCurrentYield = null;
    public ?float $annualTotalReturn = null;
    public ?float $weightedAverageMaturity = null;
    public ?float $averageAnnualReturn1Yr = null;
    public ?float $averageAnnualReturn3Yr = null;
    public ?float $averageAnnualReturn5Yr = null;
    public ?float $averageAnnualReturn10Yr = null;
    public ?float $high52 = null;
    public ?float $low52 = null;
    public ?Carbon $week52LowDate = null;
    public ?Carbon $week52HiDate = null;
    public ?string $exchangeName = null;
    public ?float $sinceInception = null;
    public ?float $quarterlySinceInception = null;
    public ?float $lastTrade = null;
    public ?float $actual12B1Fee = null;
    public ?Carbon $performanceAsOfDate = null;
    public ?Carbon $qtrlyPerformanceAsOfDate = null;
    public ?RedemptionDTO $redemption = null;
    public ?string $morningStarCategory = null;
    public ?float $monthlyTrailingReturn1Y = null;
    public ?float $monthlyTrailingReturn3Y = null;
    public ?float $monthlyTrailingReturn5Y = null;
    public ?float $monthlyTrailingReturn10Y = null;
    public ?string $etradeEarlyRedemptionFee = null;
    public ?float $maxSalesLoad = null;
    public ?float $monthlyTrailingReturnYTD = null;
    public ?float $monthlyTrailingReturn1M = null;
    public ?float $monthlyTrailingReturn3M = null;
    public ?float $monthlyTrailingReturn6M = null;
    public ?float $qtrlyTrailingReturnYTD = null;
    public ?float $qtrlyTrailingReturn1M = null;
    public ?float $qtrlyTrailingReturn3M = null;
    public ?float $qtrlyTrailingReturn6M = null;
    /**
     * @var SaleChargeValuesDTO[]
     */
    public array $deferredSalesCharges = [];
    /**
     * @var SaleChargeValuesDTO[]
     */
    public array $frontEndSalesCharges = [];
    public ?string $exchangeCode = null;

    /**
     * @param array $data
     * @return void
     */
    protected function fill(array $data): void
    {
        $netAssets = $data['netAssets'] ?? $data['NetAssets'] ?? null;
        $redemption = $data['redemption'] ?? $data['Redemption'] ?? null;
        $deferredSalesCharges = $data['deferredSalesCharges'] ?? $data['DeferredSalesCharges'] ?? null;
        $frontEndSalesCharges = $data['frontEndSalesCharges'] ?? $data['FrontEndSalesCharges'] ?? null;

        unset($data['netAssets'], $data['NetAssets'], $data['redemption'], $data['Redemption']);

        parent::fill($data);

        if ($netAssets !== null) {
            $this->netAssets = new NetAssetDTO($netAssets);
        }

        if ($redemption !== null) {
            $this->redemption = new RedemptionDTO($redemption);
        }

        if ($deferredSalesCharges !== null) {
            $this->deferredSalesCharges = array_map(
                fn ($charge) => new SaleChargeValuesDTO($charge),
                $this->normalizeChargeArray($deferredSalesCharges['saleChargeValues'] ?? $deferredSalesCharges['SaleChargeValues'] ?? $deferredSalesCharges)
            );
        }

        if ($frontEndSalesCharges !== null) {
            $this->frontEndSalesCharges = array_map(
                fn ($charge) => new SaleChargeValuesDTO($charge),
                $this->normalizeChargeArray($frontEndSalesCharges['saleChargeValues'] ?? $frontEndSalesCharges['SaleChargeValues'] ?? $frontEndSalesCharges)
            );
        }
    }

    /**
     * @param mixed $chargeData
     * @return array
     */
    private function normalizeChargeArray(mixed $chargeData): array
    {
        if (!is_array($chargeData) || empty($chargeData)) {
            return [];
        }

        if (array_keys($chargeData) !== range(0, count($chargeData) - 1)) {
            return [$chargeData];
        }

        return $chargeData;
    }
}
