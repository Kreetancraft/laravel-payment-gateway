<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreetancraft\PaymentGateway\Actions\ChargePaymentAction;
use Kreetancraft\PaymentGateway\Contracts\GatewayResolver;
use Kreetancraft\PaymentGateway\Contracts\PaymentGateway;
use Kreetancraft\PaymentGateway\Data\PaymentResult;
use Kreetancraft\PaymentGateway\Tests\Fixtures\Models\TestInvoice;

uses(RefreshDatabase::class);

/**
 * What the person paying is allowed to decide.
 *
 * `POST /payment/checkout` is public — a buyer who is not signed in has to be
 * able to reach it — and the whole request body used to be spread straight into
 * the gateway. Most of it is harmless data. Two fields are decisions that belong
 * to the merchant, and letting a caller set them is how a public endpoint turns
 * into a liability.
 */
function capturingGateway(?callable $capture = null): void
{
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('supportsCurrency')->andReturn(true);
    $gateway->shouldReceive('charge')->andReturnUsing(function (array $data) use ($capture): PaymentResult {
        if ($capture !== null) {
            $capture($data);
        }

        return PaymentResult::success(orderReference: 'ref_1', redirectUrl: 'https://gateway.example/pay');
    });

    $resolver = Mockery::mock(GatewayResolver::class);
    $resolver->shouldReceive('getDefaultDriver')->andReturn('stripe');
    $resolver->shouldReceive('resolve')->andReturn($gateway);
    app()->instance(GatewayResolver::class, $resolver);
}

it('will not let the request turn off 3-D Secure', function (): void {
    // Turning 3DS off moves chargeback liability onto the merchant. A public
    // caller could previously do it with one field in the body.
    $seen = [];
    capturingGateway(function (array $data) use (&$seen): void {
        $seen = $data;
    });

    $invoice = TestInvoice::create(['number' => 'INV-3DS', 'currency' => 'USD', 'total_cents' => 500]);

    ChargePaymentAction::run([
        'payable_type' => 'invoice',
        'payable_id' => $invoice->id,
        'request_3ds' => false,
    ]);

    expect($seen)->not->toHaveKey('request_3ds');
});

it('will not send the buyer somewhere else after paying', function (): void {
    // return_url becomes the gateway's success_url. An arbitrary value is an
    // open redirect off the merchant's own checkout, and it hands the gateway's
    // session id to whoever chose the address.
    $seen = [];
    capturingGateway(function (array $data) use (&$seen): void {
        $seen = $data;
    });

    $invoice = TestInvoice::create(['number' => 'INV-RU', 'currency' => 'USD', 'total_cents' => 500]);

    ChargePaymentAction::run([
        'payable_type' => 'invoice',
        'payable_id' => $invoice->id,
        'return_url' => 'https://not-our-site.example/collect',
    ]);

    expect($seen)->not->toHaveKey('return_url');
});

it('still honours a return url pointing back at this application', function (): void {
    // A host legitimately uses this to land the buyer on its own page.
    config()->set('app.url', 'https://shop.example');

    $seen = [];
    capturingGateway(function (array $data) use (&$seen): void {
        $seen = $data;
    });

    $invoice = TestInvoice::create(['number' => 'INV-OK', 'currency' => 'USD', 'total_cents' => 500]);

    ChargePaymentAction::run([
        'payable_type' => 'invoice',
        'payable_id' => $invoice->id,
        'return_url' => 'https://shop.example/thanks',
    ]);

    expect($seen['return_url'])->toBe('https://shop.example/thanks');
});

it('still honours a relative return url', function (): void {
    $seen = [];
    capturingGateway(function (array $data) use (&$seen): void {
        $seen = $data;
    });

    $invoice = TestInvoice::create(['number' => 'INV-REL', 'currency' => 'USD', 'total_cents' => 500]);

    ChargePaymentAction::run([
        'payable_type' => 'invoice',
        'payable_id' => $invoice->id,
        'return_url' => '/thanks',
    ]);

    expect($seen['return_url'])->toBe('/thanks');
});
