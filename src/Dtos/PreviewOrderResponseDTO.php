<?php

namespace KevinRider\LaravelEtrade\Dtos;

use KevinRider\LaravelEtrade\Dtos\Orders\CashBuyingPowerDetailsDTO;
use KevinRider\LaravelEtrade\Dtos\Orders\DisclosureDTO;
use KevinRider\LaravelEtrade\Dtos\Orders\DtBuyingPowerDetailsDTO;
use KevinRider\LaravelEtrade\Dtos\Orders\MarginBuyingPowerDetailsDTO;
use KevinRider\LaravelEtrade\Dtos\Orders\MessagesDTO;
use KevinRider\LaravelEtrade\Dtos\Orders\OrderDetailDTO;
use KevinRider\LaravelEtrade\Dtos\Orders\PortfolioMarginDTO;
use KevinRider\LaravelEtrade\Dtos\Orders\PreviewIdDTO;

class PreviewOrderResponseDTO extends BaseDTO
{
    public ?string $orderType = null;
    public ?MessagesDTO $messageList = null;
    public ?float $totalOrderValue = null;
    public ?float $totalCommission = null;
    /**
     * @var OrderDetailDTO[]
     */
    public array $order = [];
    /**
     * @var PreviewIdDTO[]
     */
    public array $previewIds = [];
    public ?int $previewTime = null;
    public ?bool $dstFlag = null;
    public ?string $accountId = null;
    public ?int $optionLevelCd = null;
    public ?string $marginLevelCd = null;
    public ?PortfolioMarginDTO $portfolioMargin = null;
    public ?bool $isEmployee = null;
    public ?string $commissionMessage = null;
    public ?DisclosureDTO $disclosure = null;
    public ?string $clientOrderId = null;
    public ?MarginBuyingPowerDetailsDTO $marginBpDetails = null;
    public ?CashBuyingPowerDetailsDTO $cashBpDetails = null;
    public ?DtBuyingPowerDetailsDTO $dtBpDetails = null;

    /**
     * @param array $data
     * @return void
     */
    protected function fill(array $data): void
    {
        $messageList = $data['messageList'] ?? $data['MessageList'] ?? $data['messages'] ?? $data['Messages'] ?? null;
        $orders = $data['order'] ?? $data['Order'] ?? null;
        $previewIds = $data['previewIds'] ?? $data['PreviewIds'] ?? null;
        $portfolioMargin = $data['portfolioMargin'] ?? $data['PortfolioMargin'] ?? null;
        $disclosure = $data['disclosure'] ?? $data['Disclosure'] ?? null;
        $marginBpDetails = $data['marginBpDetails'] ?? $data['MarginBpDetails'] ?? null;
        $cashBpDetails = $data['cashBpDetails'] ?? $data['CashBpDetails'] ?? null;
        $dtBpDetails = $data['dtBpDetails'] ?? $data['DtBpDetails'] ?? null;

        unset(
            $data['messageList'],
            $data['MessageList'],
            $data['messages'],
            $data['Messages'],
            $data['order'],
            $data['Order'],
            $data['previewIds'],
            $data['PreviewIds'],
            $data['portfolioMargin'],
            $data['PortfolioMargin'],
            $data['disclosure'],
            $data['Disclosure'],
            $data['marginBpDetails'],
            $data['MarginBpDetails'],
            $data['cashBpDetails'],
            $data['CashBpDetails'],
            $data['dtBpDetails'],
            $data['DtBpDetails']
        );

        parent::fill($data);

        if ($messageList !== null) {
            $messagesArray = $messageList['message'] ?? $messageList['Message'] ?? $messageList;
            $this->messageList = new MessagesDTO(['message' => $messagesArray]);
        }

        if ($orders !== null) {
            $ordersArray = $this->normalizeArray($orders);
            $this->order = array_map(
                fn ($order) => new OrderDetailDTO($order),
                $ordersArray
            );
        }

        if ($previewIds !== null) {
            $previewIdArray = $previewIds['previewId'] ?? $previewIds['PreviewId'] ?? $previewIds;
            $previewIdArray = $this->normalizeArray($previewIdArray);
            $this->previewIds = array_map(
                fn ($previewId) => new PreviewIdDTO(is_array($previewId) ? $previewId : ['previewId' => $previewId]),
                $previewIdArray
            );
        }

        if ($portfolioMargin !== null) {
            $this->portfolioMargin = new PortfolioMarginDTO($portfolioMargin);
        }

        if ($disclosure !== null) {
            $this->disclosure = new DisclosureDTO($disclosure);
        }

        if ($marginBpDetails !== null) {
            $this->marginBpDetails = new MarginBuyingPowerDetailsDTO($marginBpDetails);
        }

        if ($cashBpDetails !== null) {
            $this->cashBpDetails = new CashBuyingPowerDetailsDTO($cashBpDetails);
        }

        if ($dtBpDetails !== null) {
            $this->dtBpDetails = new DtBuyingPowerDetailsDTO($dtBpDetails);
        }
    }

    /**
     * @param mixed $items
     * @return array
     */
    private function normalizeArray(mixed $items): array
    {
        if ($items === null) {
            return [];
        }

        if (!is_array($items)) {
            return [$items];
        }

        if (empty($items)) {
            return [];
        }

        if (array_keys($items) !== range(0, count($items) - 1)) {
            return [$items];
        }

        return $items;
    }


    /**
     * @param string $json
     * @return static
     */
    public static function fromJson(string $json): static
    {
        $data = json_decode($json, true);

        return new static($data['PreviewOrderResponse']);
    }
}
