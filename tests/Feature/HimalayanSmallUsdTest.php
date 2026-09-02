<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreetancraft\PaymentGateway\Gateways\HimalayanBankGateway;
use Kreetancraft\PaymentGateway\Models\Gateway;
use Kreetancraft\PaymentGateway\Support\HblClient;

uses(RefreshDatabase::class);

/**
 * Small USD amounts, which is what the HBL account actually takes.
 *
 * Live transactions on this account run at roughly USD 1–10, so the amounts
 * that matter are the small ones — and small amounts are exactly where a
 * currency or unit mistake hides. A hundredth of a rupee is nothing; a
 * hundredth of a dollar on a one-dollar charge is one percent of the payment.
 *
 * There was a real bug here: `buildDefaultPurchaseItems()` took a currency
 * argument and ignored it, writing NPR into every purchase item whatever the
 * payment was denominated in. The transaction block said USD and the line items
 * said NPR, in the same request.
 */
function hblUsdGateway(): Gateway
{
    return Gateway::updateOrCreate(['code' => 'himalayan'], [
        'label' => 'Himalayan Bank',
        'enabled' => true,
        'class' => HimalayanBankGateway::class,
        'currencies' => ['NPR', 'USD'],
        'credentials' => [
            'office_id' => 'DEMOOFFICE',
            'api_key' => 'test-api-key',
            'encryption_key_id' => '7664a2ed0dee4879bdfca0e8ce1ac313',
        ],
    ]);
}

/**
 * Capture the request the driver would send to PACO.
 *
 * @return array<string, mixed>
 */
function hblChargePayload(int $amountCents, string $currency = 'USD'): array
{
    $sent = [];

    $client = Mockery::mock(HblClient::class);
    $client->shouldReceive('prePaymentUi')->andReturnUsing(function (array $payload) use (&$sent): array {
        $sent = $payload;

        return ['response' => ['Data' => ['paymentPage' => ['paymentPageURL' => 'https://demo/pay']]]];
    });
    app()->instance(HblClient::class, $client);

    (new HimalayanBankGateway(hblUsdGateway(), $client))->charge([
        'amount_cents' => $amountCents,
        'currency' => $currency,
        'description' => 'Small USD charge',
        'order_reference' => 'ORD-USD',
    ]);

    return $sent;
}

it('sends one dollar as one dollar', function (): void {
    $payload = hblChargePayload(100);

    expect($payload['transactionAmount']['amount'])->toBe(1.0)
        ->and($payload['transactionAmount']['currencyCode'])->toBe('USD')
        ->and($payload['transactionAmount']['decimalPlaces'])->toBe(2)
        // PACO wants the minor units, left-padded to twelve characters.
        ->and($payload['transactionAmount']['amountText'])->toBe('000000000100');
});

it('keeps the cents on an amount that is not round', function (): void {
    // 9.99 is the amount most likely to expose a float or a truncation.
    $payload = hblChargePayload(999);

    expect($payload['transactionAmount']['amount'])->toBe(9.99)
        ->and($payload['transactionAmount']['amountText'])->toBe('000000000999');
});

it('denominates the line items in the currency being charged', function (): void {
    // The regression. This said NPR on a USD payment.
    $payload = hblChargePayload(250);

    expect($payload['purchaseItems'][0]['purchaseItemPrice']['currencyCode'])->toBe('USD')
        ->and($payload['purchaseItems'][0]['purchaseItemPrice']['amount'])->toBe(2.5)
        ->and($payload['purchaseItems'][0]['purchaseItemPrice']['amountText'])->toBe('000000000250');
});

it('carries the order number rather than the vendor demo reference', function (): void {
    // It shipped with '2322460376026', copied from the integration sample, so
    // every line item referenced the same non-existent order.
    $payload = hblChargePayload(500);

    expect($payload['purchaseItems'][0]['referenceNo'])
        ->toBe($payload['orderNo'])
        ->not->toBe('2322460376026');
});

it('handles the whole one-to-ten dollar range without drift', function (): void {
    foreach (range(1, 10) as $dollars) {
        $cents = $dollars * 100;
        $payload = hblChargePayload($cents);

        expect($payload['transactionAmount']['amount'])->toBe((float) $dollars)
            ->and($payload['transactionAmount']['amountText'])->toBe(str_pad((string) $cents, 12, '0', STR_PAD_LEFT))
            ->and($payload['transactionAmount']['currencyCode'])->toBe('USD');
    }
});

it('still sends NPR when the payment is in NPR', function (): void {
    $payload = hblChargePayload(1000, 'NPR');

    expect($payload['transactionAmount']['currencyCode'])->toBe('NPR')
        ->and($payload['purchaseItems'][0]['purchaseItemPrice']['currencyCode'])->toBe('NPR');
});
