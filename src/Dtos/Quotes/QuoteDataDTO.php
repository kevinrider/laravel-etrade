<?php

namespace KevinRider\LaravelEtrade\Dtos\Quotes;

use Illuminate\Support\Carbon;
use KevinRider\LaravelEtrade\Dtos\BaseDTO;

class QuoteDataDTO extends BaseDTO
{
    public ?Carbon $dateTime = null;
    public ?Carbon $dateTimeUTC = null;
    public ?string $quoteStatus = null;
    public ?bool $ahFlag = null;
    public ?string $errorMessage = null;
    public ?string $timeZone = null;
    public ?bool $dstFlag = null;
    public ?bool $hasMiniOptions = null;
    public ?AllQuoteDetailsDTO $all = null;
    public ?FundamentalQuoteDetailsDTO $fundamental = null;
    public ?IntradayQuoteDetailsDTO $intraday = null;
    public ?OptionQuoteDetailsDTO $option = null;
    public ?ProductDTO $product = null;
    public ?Week52QuoteDetailsDTO $week52 = null;
    public ?MutualFundDTO $mutualFund = null;

    /**
     * @param array $data
     * @return void
     */
    protected function fill(array $data): void
    {
        parent::fill($data);

        if (isset($data['All'])) {
            $this->all = new AllQuoteDetailsDTO($data['All']);
        }
        if (isset($data['Fundamental'])) {
            $this->fundamental = new FundamentalQuoteDetailsDTO($data['Fundamental']);
        }
        if (isset($data['Intraday'])) {
            $this->intraday = new IntradayQuoteDetailsDTO($data['Intraday']);
        }
        if (isset($data['Option'])) {
            $this->option = new OptionQuoteDetailsDTO($data['Option']);
        }
        if (isset($data['Product'])) {
            $this->product = new ProductDTO($data['Product']);
        }
        if (isset($data['Week52'])) {
            $this->week52 = new Week52QuoteDetailsDTO($data['Week52']);
        }
        if (isset($data['MutualFund'])) {
            $this->mutualFund = new MutualFundDTO($data['MutualFund']);
        }
    }
}
