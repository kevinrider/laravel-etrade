<?php

namespace KevinRider\LaravelEtrade\Dtos\ListOrders;

use Illuminate\Support\Carbon;
use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class OrderDetailDTO extends BaseDTO
{
    public ?int $orderNumber = null;
    public ?string $accountId = null;
    public ?Carbon $previewTime = null;
    public ?Carbon $placedTime = null;
    public ?Carbon $executedTime = null;
    public ?float $orderValue = null;
    public ?string $status = null;
    public ?string $orderType = null;
    public ?string $orderTerm = null;
    public ?string $priceType = null;
    public ?string $priceValue = null;
    public ?float $limitPrice = null;
    public ?float $stopPrice = null;
    public ?float $stopLimitPrice = null;
    public ?string $offsetType = null;
    public ?float $offsetValue = null;
    public ?string $marketSession = null;
    public ?string $routingDestination = null;
    public ?float $bracketedLimitPrice = null;
    public ?float $initialStopPrice = null;
    public ?float $trailPrice = null;
    public ?float $triggerPrice = null;
    public ?float $conditionPrice = null;
    public ?string $conditionSymbol = null;
    public ?string $conditionType = null;
    public ?string $conditionFollowPrice = null;
    public ?string $conditionSecurityType = null;
    public ?int $replacedByOrderId = null;
    public ?int $replacesOrderId = null;
    public ?bool $allOrNone = null;
    public ?int $previewId = null;
    /**
     * @var InstrumentDTO[]
     */
    public array $instrument = [];
    public ?MessagesDTO $messages = null;
    public ?float $investmentAmount = null;
    public ?string $positionQuantity = null;
    public ?bool $aipFlag = null;
    public ?string $egQual = null;
    public ?string $reInvestOption = null;
    public ?float $estimatedCommission = null;
    public ?float $estimatedFees = null;
    public ?float $estimatedTotalAmount = null;
    public ?float $netPrice = null;
    public ?float $netBid = null;
    public ?float $netAsk = null;
    public ?int $gcd = null;
    public ?string $ratio = null;
    public ?string $mfpriceType = null;

    /**
     * @param array $data
     * @return void
     */
    protected function fill(array $data): void
    {
        $instruments = $data['instrument'] ?? $data['Instrument'] ?? null;
        $messages = $data['messages'] ?? $data['Messages'] ?? null;

        unset($data['instrument'], $data['Instrument'], $data['messages'], $data['Messages']);

        parent::fill($data);

        if ($instruments !== null) {
            if (isset($instruments['Instrument'])) {
                $instruments = $instruments['Instrument'];
            }

            $instruments = $this->normalizeInstrumentArray($instruments);
            $this->instrument = array_map(
                fn ($instrument) => new InstrumentDTO($instrument),
                $instruments
            );
        }

        if ($messages !== null) {
            $this->messages = new MessagesDTO($messages);
        }
    }

    /**
     * @param mixed $instruments
     * @return array
     */
    private function normalizeInstrumentArray(mixed $instruments): array
    {
        if (!is_array($instruments) || empty($instruments)) {
            return [];
        }

        if (array_keys($instruments) !== range(0, count($instruments) - 1)) {
            return [$instruments];
        }

        return $instruments;
    }
}
