<?php

use KevinRider\LaravelEtrade\Dtos\Orders\DisclosureDTO;
use KevinRider\LaravelEtrade\Dtos\Orders\PreviewIdDTO;
use KevinRider\LaravelEtrade\EtradeOrderBuilder;

function orderFixture(string $filename): array
{
    $path = __DIR__ . '/../fixtures/' . $filename;

    return json_decode(file_get_contents($path), true);
}

/**
 * Normalize payload scalars to strings for easy fixture comparison.
 *
 * @param mixed $value
 * @param array $path
 * @return mixed
 */
function canonicalizePayload(mixed $value, array $path = []): mixed
{
    if (is_array($value)) {
        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[$key] = canonicalizePayload($item, [...$path, $key]);
        }
        return $normalized;
    }

    $currentKey = end($path) ?: null;

    if (in_array($currentKey, ['expiryMonth', 'expiryDay'], true)) {
        return str_pad((string) $value, 2, '0', STR_PAD_LEFT);
    }

    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return $value;
}

function normalizeForFixture(array $payload): array
{
    return canonicalizePayload($payload);
}

it('builds preview equity order payload', function () {
    $builder = EtradeOrderBuilder::forAccount('ACC123')
        ->orderType('EQ')
        ->clientOrderId('1fds311')
        ->withSymbol('FB')
        ->quantityType('QUANTITY')
        ->gfd()
        ->priceType('LIMIT')
        ->limitPrice(169)
        ->marketSession('REGULAR')
        ->allOrNone(false)
        ->addEquity('BUY');

    $payload = $builder->buildPreviewRequest()->toRequestBody();

    expect(normalizeForFixture($payload))->toEqual(orderFixture('PreviewOrderRequestEquity.json'));
});

it('carries orderId onto preview and place requests for change orders', function () {
    $builder = EtradeOrderBuilder::forAccount('ACC123')
        ->orderType('EQ')
        ->clientOrderId('1fds311')
        ->orderId(825)
        ->withSymbol('FB')
        ->quantityType('QUANTITY')
        ->gfd()
        ->priceType('LIMIT')
        ->limitPrice(169)
        ->marketSession('REGULAR')
        ->allOrNone(false)
        ->addEquity('BUY');

    $preview = $builder->buildPreviewRequest();
    $place = $builder->buildPlaceRequest([new PreviewIdDTO(['previewId' => 3429395279])]);

    expect($preview->orderId)->toBe(825)
        ->and($place->orderId)->toBe(825);
});

it('builds place equity order payload with normalized preview ids', function () {
    $builder = EtradeOrderBuilder::forAccount('ACC123')
        ->orderType('EQ')
        ->clientOrderId('1fds311')
        ->withSymbol('FB')
        ->quantityType('QUANTITY')
        ->gfd()
        ->priceType('LIMIT')
        ->limitPrice(169)
        ->marketSession('REGULAR')
        ->allOrNone(false)
        ->addEquity('BUY');

    $request = $builder->buildPlaceRequest([new PreviewIdDTO(['previewId' => 3429395279])]);

    expect(normalizeForFixture($request->toRequestBody()))->toEqual(orderFixture('PlaceOrderRequestEquity.json'));
});

it('builds preview options order payload using option helpers', function () {
    $builder = EtradeOrderBuilder::forAccount('ACCT')
        ->orderType('OPTN')
        ->clientOrderId('8e4153f1')
        ->withSymbol('FB')
        ->withExpiry(2018, 12, 21)
        ->market()
        ->limitPrice(5)
        ->stopPrice(0)
        ->gfd()
        ->marketSession('REGULAR')
        ->allOrNone(false)
        ->addLongCall(140);

    $payload = $builder->buildPreviewRequest()->toRequestBody();

    expect(normalizeForFixture($payload))->toEqual(orderFixture('PreviewOrderRequestOptions.json'));
});

it('builds place options order payload and accepts array preview ids', function () {
    $builder = EtradeOrderBuilder::forAccount('ACCT')
        ->orderType('OPTN')
        ->clientOrderId('8e4153f1')
        ->withSymbol('FB')
        ->withExpiry(2018, 12, 21)
        ->market()
        ->limitPrice(5)
        ->stopPrice(0)
        ->gfd()
        ->marketSession('REGULAR')
        ->allOrNone(false)
        ->addLongCall(140);

    $request = $builder->buildPlaceRequest([['previewId' => '2785277279']]);

    expect(normalizeForFixture($request->toRequestBody()))->toEqual(orderFixture('PlaceOrderRequestOptions.json'));
});

it('builds preview spread order payload', function () {
    $builder = EtradeOrderBuilder::forAccount('ACC')
        ->orderType('SPREADS')
        ->clientOrderId('3453f1')
        ->withSymbol('IBM')
        ->withExpiry(2019, 2, 15)
        ->netDebit(5)
        ->stopPrice(0)
        ->gfd()
        ->marketSession('REGULAR')
        ->allOrNone(false)
        ->addLongCall(130)
        ->addShortCall(131);

    $payload = $builder->buildPreviewRequest()->toRequestBody();

    expect(normalizeForFixture($payload))->toEqual(orderFixture('PreviewOrderRequestSpread.json'));
});

it('builds place spread order payload', function () {
    $builder = EtradeOrderBuilder::forAccount('ACC')
        ->orderType('SPREADS')
        ->clientOrderId('3453f1')
        ->withSymbol('IBM')
        ->withExpiry(2019, 2, 15)
        ->netDebit(5)
        ->stopPrice(0)
        ->gfd()
        ->marketSession('REGULAR')
        ->allOrNone(false)
        ->addLongCall(130)
        ->addShortCall(131);

    $request = $builder->buildPlaceRequest([3429218279]);

    expect(normalizeForFixture($request->toRequestBody()))->toEqual(orderFixture('PlaceOrderRequestSpread.json'));
});

it('applies overrides, disclosures, and stop limits to option orders', function () {
    $builder = EtradeOrderBuilder::forAccount('ANY')
        ->orderType('OPTN')
        ->clientOrderId('abc123')
        ->withSymbol('TSLA')
        ->withExpiry(2024, 6, 21)
        ->gtc()
        ->netCredit(2.5)
        ->stopLimitPrice(2.1)
        ->marketSession('EXTENDED')
        ->allOrNone(true)
        ->disclosure(new DisclosureDTO(['ehDisclosureFlag' => true, 'aoDisclosureFlag' => true]))
        ->withDetail(['routingDestination' => 'AUTO'])
        ->addLongPut(100, 2, ['quantityType' => 'DOLLAR'])
        ->addShortPut(90, 1.5, ['quantityType' => 'ALL_I_OWN', 'orderedQuantity' => 1.5]);

    $order = $builder->buildPreviewRequest()->toRequestBody()['PreviewOrderRequest']['Order'][0];

    expect($order['priceType'])->toBe('NET_CREDIT')
        ->and($order['limitPrice'])->toBe(2.5)
        ->and($order['stopLimitPrice'])->toBe(2.1)
        ->and($order['orderTerm'])->toBe('GOOD_UNTIL_CANCEL')
        ->and($order['marketSession'])->toBe('EXTENDED')
        ->and($order['allOrNone'])->toBeTrue()
        ->and($order['routingDestination'])->toBe('AUTO')
        ->and($order['stopPrice'])->toBe('')
        ->and($order['Instrument'])->toHaveCount(2)
        ->and($order['Instrument'][0]['Product']['callPut'])->toBe('PUT')
        ->and($order['Instrument'][0]['quantityType'])->toBe('DOLLAR')
        ->and($order['Instrument'][0]['quantity'])->toBe(2.0)
        ->and($order['Instrument'][0]['orderAction'])->toBe('BUY_OPEN')
        ->and($order['Instrument'][1]['Product']['callPut'])->toBe('PUT')
        ->and($order['Instrument'][1]['quantityType'])->toBe('ALL_I_OWN')
        ->and($order['Instrument'][1]['orderedQuantity'])->toBe(1.5)
        ->and($order['Instrument'][1]['orderAction'])->toBe('SELL_OPEN');

});

it('validates preview and place requirements', function () {
    expect(fn () => (new EtradeOrderBuilder())->buildPreviewRequest())
        ->toThrow(InvalidArgumentException::class, 'accountIdKey is required.');

    $builder = EtradeOrderBuilder::forAccount('ID')
        ->orderType('EQ')
        ->clientOrderId('CID')
        ->addInstrument([
            'orderAction' => 'BUY',
            'quantityType' => 'QUANTITY',
            'quantity' => 1,
            'product' => ['securityType' => 'EQ', 'symbol' => 'AAPL'],
        ]);

    expect(fn () => $builder->buildPlaceRequest([]))
        ->toThrow(InvalidArgumentException::class, 'At least one previewId is required to place an order.');
});

it('validates preview id entries when placing orders', function () {
    $builder = EtradeOrderBuilder::forAccount('ID')
        ->orderType('EQ')
        ->clientOrderId('CID')
        ->withSymbol('AAPL')
        ->quantityType('QUANTITY')
        ->addEquity('BUY');

    expect(fn () => $builder->buildPlaceRequest([['notPreviewId' => 1]]))
        ->toThrow(InvalidArgumentException::class, 'previewId must be provided for each previewIds entry.')
        ->and(fn () => $builder->buildPlaceRequest(['not-an-id']))
        ->toThrow(InvalidArgumentException::class, 'previewIds must be PreviewIdDTO, array with previewId, or integer preview id.');

});

it('requires symbol and expiry when adding option legs', function () {
    $builderWithoutSymbol = EtradeOrderBuilder::forAccount('ID')
        ->orderType('OPTN')
        ->clientOrderId('CID');

    expect(fn () => $builderWithoutSymbol->addLongCall(100))
        ->toThrow(InvalidArgumentException::class, 'Symbol is required for option legs. Use withSymbol() or pass symbol override.');

    $builderWithoutExpiry = EtradeOrderBuilder::forAccount('ID')
        ->orderType('OPTN')
        ->clientOrderId('CID')
        ->withSymbol('AAPL');

    expect(fn () => $builderWithoutExpiry->addLongCall(100))
        ->toThrow(InvalidArgumentException::class, 'Expiry year, month, and day are required for option legs. Use withExpiry() or pass expiry overrides.');
});

it('validates expiry dates', function () {
    $builder = EtradeOrderBuilder::forAccount('ID')
        ->orderType('OPTN')
        ->clientOrderId('CID');

    expect(fn () => $builder->withExpiry(2024, 13, 1))
        ->toThrow(InvalidArgumentException::class, 'Expiry date must be a valid calendar date.');

    $builderWithSymbol = EtradeOrderBuilder::forAccount('ID')
        ->orderType('OPTN')
        ->clientOrderId('CID')
        ->withSymbol('AAPL');

    expect(fn () => $builderWithSymbol->addLongCall(100, 1, ['expiryYear' => 2024, 'expiryMonth' => 2, 'expiryDay' => 30]))
        ->toThrow(InvalidArgumentException::class, 'Expiry date must be a valid calendar date.');
});

it('normalizes empty stop prices to blank strings', function () {
    $builder = EtradeOrderBuilder::forAccount('ID')
        ->orderType('EQ')
        ->clientOrderId('CID')
        ->withDetail(['stopPrice' => ''])
        ->withSymbol('AAPL')
        ->quantityType('QUANTITY')
        ->addEquity('BUY');

    $order = $builder->buildPreviewRequest()->toRequestBody()['PreviewOrderRequest']['Order'][0];

    expect($order['stopPrice'])->toBe('');
});

it('validates quantity types for defaults and overrides', function () {
    expect(fn () => (new EtradeOrderBuilder())->quantityType('INVALID'))
        ->toThrow(InvalidArgumentException::class, 'quantityType must be one of: QUANTITY, DOLLAR, ALL_I_OWN');

    $builder = EtradeOrderBuilder::forAccount('ACCT')
        ->orderType('OPTN')
        ->clientOrderId('CID')
        ->withSymbol('AAPL')
        ->withExpiry(2024, 1, 19);

    expect(fn () => $builder->addLongCall(100, 1, ['quantityType' => 'NOT_ALLOWED']))
        ->toThrow(InvalidArgumentException::class, 'quantityType must be one of: QUANTITY, DOLLAR, ALL_I_OWN');

    $builder->quantityType('QUANTITY');

    $equity = EtradeOrderBuilder::forAccount('EQ')
        ->orderType('EQ')
        ->clientOrderId('CID')
        ->withSymbol('AAPL')
        ->quantityType('ALL_I_OWN')
        ->addEquity('SELL');

    $instrument = $equity->buildPreviewRequest()->toRequestBody()['PreviewOrderRequest']['Order'][0]['Instrument'][0];

    expect($instrument['quantityType'])->toBe('ALL_I_OWN');
});

it('validates order type values', function () {
    expect(fn () => EtradeOrderBuilder::forAccount('ACC')->orderType('INVALID'))
        ->toThrow(
            InvalidArgumentException::class,
            'orderType must be one of: EQ, OPTN, SPREADS, BUY_WRITES, BUTTERFLY, IRON_BUTTERFLY, CONDOR, IRON_CONDOR, MF, MMF'
        );
});

it('validates order term values', function () {
    $builder = EtradeOrderBuilder::forAccount('ACC')
        ->orderType('EQ')
        ->clientOrderId('CID')
        ->withSymbol('AAPL')
        ->quantityType('QUANTITY')
        ->addEquity('BUY');

    expect(fn () => $builder->term('NOT_A_TERM'))
        ->toThrow(
            InvalidArgumentException::class,
            'orderTerm must be one of: GOOD_UNTIL_CANCEL, GOOD_FOR_DAY, GOOD_TILL_DATE, IMMEDIATE_OR_CANCEL, FILL_OR_KILL'
        );
});

it('validates price type values', function () {
    $builder = EtradeOrderBuilder::forAccount('ACC')
        ->orderType('EQ')
        ->clientOrderId('CID')
        ->withSymbol('AAPL')
        ->quantityType('QUANTITY')
        ->addEquity('BUY');

    expect(fn () => $builder->priceType('NOT_A_PRICE'))
        ->toThrow(
            InvalidArgumentException::class,
            'priceType must be one of: MARKET, LIMIT, STOP, STOP_LIMIT, TRAILING_STOP_CNST_BY_LOWER_TRIGGER, UPPER_TRIGGER_BY_TRAILING_STOP_CNST, TRAILING_STOP_PRCT_BY_LOWER_TRIGGER, UPPER_TRIGGER_BY_TRAILING_STOP_PRCT, TRAILING_STOP_CNST, TRAILING_STOP_PRCT, HIDDEN_STOP, HIDDEN_STOP_BY_LOWER_TRIGGER, UPPER_TRIGGER_BY_HIDDEN_STOP, NET_DEBIT, NET_CREDIT, NET_EVEN, MARKET_ON_OPEN, MARKET_ON_CLOSE, LIMIT_ON_OPEN, LIMIT_ON_CLOSE'
        );
});

it('validates market session values', function () {
    $builder = EtradeOrderBuilder::forAccount('ACC')
        ->orderType('EQ')
        ->clientOrderId('CID')
        ->withSymbol('AAPL')
        ->quantityType('QUANTITY')
        ->addEquity('BUY');

    expect(fn () => $builder->marketSession('NOT_A_SESSION'))
        ->toThrow(InvalidArgumentException::class, 'marketSession must be one of: REGULAR, EXTENDED');
});

it('validates order action values', function () {
    $builder = EtradeOrderBuilder::forAccount('ACC')
        ->orderType('EQ')
        ->clientOrderId('CID')
        ->withSymbol('AAPL')
        ->quantityType('QUANTITY');

    expect(fn () => $builder->addEquity('NOT_AN_ACTION'))
        ->toThrow(
            InvalidArgumentException::class,
            'orderAction must be one of: BUY, SELL, BUY_TO_COVER, SELL_SHORT, BUY_OPEN, BUY_CLOSE, SELL_OPEN, SELL_CLOSE, EXCHANGE'
        );
});

it('validates security type values', function () {
    $builder = EtradeOrderBuilder::forAccount('ACC')
        ->orderType('EQ')
        ->clientOrderId('CID')
        ->withSymbol('AAPL')
        ->quantityType('QUANTITY');

    expect(fn () => $builder->addEquity('BUY', 1, ['securityType' => 'INVALID']))
        ->toThrow(InvalidArgumentException::class, 'securityType must be one of: EQ, OPTN, MF, MMF');

    $optionsBuilder = EtradeOrderBuilder::forAccount('ACC')
        ->orderType('OPTN')
        ->clientOrderId('CID')
        ->withSymbol('AAPL')
        ->withExpiry(2024, 1, 19)
        ->quantityType('QUANTITY');

    expect(fn () => $optionsBuilder->addLongCall(100, 1, ['securityType' => 'INVALID']))
        ->toThrow(InvalidArgumentException::class, 'securityType must be one of: EQ, OPTN, MF, MMF');
});
