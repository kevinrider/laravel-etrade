<?php

use KevinRider\LaravelEtrade\Dtos\Orders\DisclosureDTO;
use KevinRider\LaravelEtrade\Dtos\Orders\PreviewIdDTO;
use KevinRider\LaravelEtrade\OrderBuilder;

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
    $builder = OrderBuilder::forAccount('ACC123')
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

it('builds place equity order payload with normalized preview ids', function () {
    $builder = OrderBuilder::forAccount('ACC123')
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
    $builder = OrderBuilder::forAccount('ACCT')
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
    $builder = OrderBuilder::forAccount('ACCT')
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
    $builder = OrderBuilder::forAccount('ACC')
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
    $builder = OrderBuilder::forAccount('ACC')
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

    $request = $builder->buildPlaceRequest(['3429218279']);

    expect(normalizeForFixture($request->toRequestBody()))->toEqual(orderFixture('PlaceOrderRequestSpread.json'));
});

it('applies overrides, disclosures, and stop limits to option orders', function () {
    $builder = OrderBuilder::forAccount('ANY')
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
    expect(fn () => (new OrderBuilder())->buildPreviewRequest())
        ->toThrow(InvalidArgumentException::class, 'accountIdKey is required.');

    $builder = OrderBuilder::forAccount('ID')
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

it('requires symbol and expiry when adding option legs', function () {
    $builderWithoutSymbol = OrderBuilder::forAccount('ID')
        ->orderType('OPTN')
        ->clientOrderId('CID');

    expect(fn () => $builderWithoutSymbol->addLongCall(100))
        ->toThrow(InvalidArgumentException::class, 'Symbol is required for option legs. Use withSymbol() or pass symbol override.');

    $builderWithoutExpiry = OrderBuilder::forAccount('ID')
        ->orderType('OPTN')
        ->clientOrderId('CID')
        ->withSymbol('AAPL');

    expect(fn () => $builderWithoutExpiry->addLongCall(100))
        ->toThrow(InvalidArgumentException::class, 'Expiry year, month, and day are required for option legs. Use withExpiry() or pass expiry overrides.');
});

it('normalizes empty stop prices to blank strings', function () {
    $builder = OrderBuilder::forAccount('ID')
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
    expect(fn () => (new OrderBuilder())->quantityType('INVALID'))
        ->toThrow(InvalidArgumentException::class, 'quantityType must be one of: QUANTITY, DOLLAR, ALL_I_OWN');

    $builder = OrderBuilder::forAccount('ACCT')
        ->orderType('OPTN')
        ->clientOrderId('CID')
        ->withSymbol('AAPL')
        ->withExpiry(2024, 1, 19);

    expect(fn () => $builder->addLongCall(100, 1, ['quantityType' => 'NOT_ALLOWED']))
        ->toThrow(InvalidArgumentException::class, 'quantityType must be one of: QUANTITY, DOLLAR, ALL_I_OWN');

    $builder->quantityType('QUANTITY');

    $equity = OrderBuilder::forAccount('EQ')
        ->orderType('EQ')
        ->clientOrderId('CID')
        ->withSymbol('AAPL')
        ->quantityType('ALL_I_OWN')
        ->addEquity('SELL');

    $instrument = $equity->buildPreviewRequest()->toRequestBody()['PreviewOrderRequest']['Order'][0]['Instrument'][0];

    expect($instrument['quantityType'])->toBe('ALL_I_OWN');
});
