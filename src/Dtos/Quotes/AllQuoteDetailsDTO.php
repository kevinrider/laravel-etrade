<?php

namespace KevinRider\LaravelEtrade\Dtos\Quotes;

use Illuminate\Support\Carbon;
use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class AllQuoteDetailsDTO extends BaseDTO
{
    public ?bool $adjustedFlag = null;
    public ?float $ask = null;
    public ?int $askSize = null;
    public ?Carbon $askTime = null;
    public ?float $bid = null;
    public ?string $bidExchange = null;
    public ?int $bidSize = null;
    public ?Carbon $bidTime = null;
    public ?float $changeClose = null;
    public ?float $changeClosePercentage = null;
    public ?string $companyName = null;
    public ?int $daysToExpiration = null;
    public ?string $dirLast = null;
    public ?float $dividend = null;
    public ?float $eps = null;
    public ?float $estEarnings = null;
    public ?Carbon $exDividendDate = null;
    public ?float $high = null;
    public ?float $high52 = null;
    public ?float $lastTrade = null;
    public ?float $low = null;
    public ?float $low52 = null;
    public ?float $open = null;
    public ?int $openInterest = null;
    public ?string $optionStyle = null;
    public ?string $optionUnderlier = null;
    public ?string $optionUnderlierExchange = null;
    public ?float $previousClose = null;
    public ?int $previousDayVolume = null;
    public ?string $primaryExchange = null;
    public ?string $symbolDescription = null;
    public ?int $totalVolume = null;
    public ?int $upc = null;
    /**
     * @var OptionDeliverableDTO[]
     */
    public array $optionDeliverableList = [];
    public ?float $cashDeliverable = null;
    public ?float $marketCap = null;
    public ?float $sharesOutstanding = null;
    public ?Carbon $nextEarningDate = null;
    public ?float $beta = null;
    public ?float $yield = null;
    public ?float $declaredDividend = null;
    public ?Carbon $dividendPayableDate = null;
    public ?float $pe = null;
    public ?Carbon $week52LowDate = null;
    public ?Carbon $week52HiDate = null;
    public ?float $intrinsicValue = null;
    public ?float $timePremium = null;
    public ?float $optionMultiplier = null;
    public ?float $contractSize = null;
    public ?Carbon $expirationDate = null;
    public ?ExtendedHourQuoteDetailDTO $ehQuote = null;
    public ?float $optionPreviousBidPrice = null;
    public ?float $optionPreviousAskPrice = null;
    public ?string $osiKey = null;
    public ?Carbon $timeOfLastTrade = null;
    public ?int $averageVolume = null;

    /**
     * @param array $data
     * @return void
     */
    protected function fill(array $data): void
    {
        $deliverableList = $data['optionDeliverableList'] ?? $data['OptionDeliverableList'] ?? null;
        $ehQuote = $data['ehQuote'] ?? $data['EhQuote'] ?? null;

        unset($data['ehQuote'], $data['EhQuote']);

        parent::fill($data);

        if ($deliverableList !== null) {
            $deliverableData = $deliverableList['optionDeliverable'] ?? $deliverableList['OptionDeliverable'] ?? $deliverableList;
            $this->optionDeliverableList = array_map(
                fn ($deliverable) => new OptionDeliverableDTO($deliverable),
                $this->normalizeDeliverableArray($deliverableData)
            );
        }

        if ($ehQuote !== null) {
            $this->ehQuote = new ExtendedHourQuoteDetailDTO($ehQuote);
        }
    }

    /**
     * @param mixed $deliverables
     * @return array
     */
    private function normalizeDeliverableArray(mixed $deliverables): array
    {
        if (!is_array($deliverables) || empty($deliverables)) {
            return [];
        }

        if (array_keys($deliverables) !== range(0, count($deliverables) - 1)) {
            return [$deliverables];
        }

        return $deliverables;
    }
}
