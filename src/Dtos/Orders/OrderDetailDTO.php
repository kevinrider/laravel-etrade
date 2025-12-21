<?php

namespace KevinRider\LaravelEtrade\Dtos\Orders;

use Illuminate\Support\Carbon;
use KevinRider\LaravelEtrade\Dtos\BaseDTO;
use ReflectionClass;
use ReflectionProperty;

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
        $messages = $data['messages'] ?? $data['Messages'] ?? $data['messageList'] ?? $data['MessageList'] ?? null;

        unset(
            $data['instrument'],
            $data['Instrument'],
            $data['messages'],
            $data['Messages'],
            $data['messageList'],
            $data['MessageList']
        );

        parent::fill($data);

        if ($instruments !== null) {
            $instrumentsArray = $this->normalizeArray($instruments);
            $this->instrument = array_map(
                fn ($instrument) => new InstrumentDTO(is_array($instrument) ? $instrument : $instrument->toArray()),
                $instrumentsArray
            );
        }

        if ($messages !== null) {
            $messagesArray = $messages['message'] ?? $messages['Message'] ?? $messages;
            $this->messages = new MessagesDTO(['message' => $messagesArray]);
        }
    }

    /**
     * @param mixed $instruments
     * @return array
     */
    private function normalizeArray(mixed $instruments): array
    {
        if (!is_array($instruments) || empty($instruments)) {
            return [];
        }

        if (array_keys($instruments) !== range(0, count($instruments) - 1)) {
            return [$instruments];
        }

        return $instruments;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $reflection = new ReflectionClass($this);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        $data = [];
        foreach ($properties as $property) {
            $data[$property->getName() == 'instrument' ? 'Instrument' : $property->getName()] = $this->{$property->getName()};
        }

        return $data;
    }
}
