<?php

namespace KevinRider\LaravelEtrade\Dtos\Response;

use Illuminate\Support\Carbon;
use KevinRider\LaravelEtrade\Dtos\BaseDTO;
use KevinRider\LaravelEtrade\Dtos\Orders\DisclosureDTO;
use KevinRider\LaravelEtrade\Dtos\Orders\MessagesDTO;
use KevinRider\LaravelEtrade\Dtos\Orders\OrderDetailDTO;
use KevinRider\LaravelEtrade\Dtos\Orders\OrderIdDTO;
use KevinRider\LaravelEtrade\Dtos\Orders\PortfolioMarginDTO;

class PlaceOrderResponseDTO extends BaseDTO
{
    public ?string $orderType = null;
    public ?MessagesDTO $messageList = null;
    public ?float $totalOrderValue = null;
    public ?float $totalCommission = null;
    public ?int $orderId = null;
    /**
     * @var OrderDetailDTO[]
     */
    public array $order = [];
    public ?bool $dstFlag = null;
    public ?int $optionLevelCd = null;
    public ?string $marginLevelCd = null;
    public ?bool $isEmployee = null;
    public ?string $commissionMsg = null;
    /**
     * @var OrderIdDTO[]
     */
    public array $orderIds = [];
    public ?Carbon $placedTime = null;
    public ?string $accountId = null;
    public ?PortfolioMarginDTO $portfolioMargin = null;
    public ?DisclosureDTO $disclosure = null;
    public ?string $clientOrderId = null;

    /**
     * @param array $data
     * @return void
     */
    protected function fill(array $data): void
    {
        $messageList = $data['messageList'] ?? $data['MessageList'] ?? $data['messages'] ?? $data['Messages'] ?? null;
        $orders = $data['order'] ?? $data['Order'] ?? null;
        $orderIds = $data['orderIds'] ?? $data['OrderIds'] ?? null;
        $portfolioMargin = $data['portfolioMargin'] ?? $data['PortfolioMargin'] ?? null;
        $disclosure = $data['disclosure'] ?? $data['Disclosure'] ?? null;

        unset(
            $data['messageList'],
            $data['MessageList'],
            $data['messages'],
            $data['Messages'],
            $data['order'],
            $data['Order'],
            $data['orderIds'],
            $data['OrderIds'],
            $data['portfolioMargin'],
            $data['PortfolioMargin'],
            $data['disclosure'],
            $data['Disclosure']
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

        if ($orderIds !== null) {
            $idsArray = $orderIds['orderId'] ?? $orderIds['OrderId'] ?? $orderIds;
            $idsArray = $this->normalizeArray($idsArray);
            $this->orderIds = array_map(
                fn ($orderId) => new OrderIdDTO(is_array($orderId) ? $orderId : ['orderId' => $orderId]),
                $idsArray
            );
        }

        if ($portfolioMargin !== null) {
            $this->portfolioMargin = new PortfolioMarginDTO($portfolioMargin);
        }

        if ($disclosure !== null) {
            $this->disclosure = new DisclosureDTO($disclosure);
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

        return new static($data['PlaceOrderResponse']);
    }
}
