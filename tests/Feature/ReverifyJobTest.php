<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Kreetancraft\PaymentGateway\Actions\ChargePaymentAction;
use Kreetancraft\PaymentGateway\Contracts\GatewayResolver;
use Kreetancraft\PaymentGateway\Contracts\PaymentGateway;
use Kreetancraft\PaymentGateway\Data\PaymentResult;
use Kreetancraft\PaymentGateway\Enums\PaymentStatus;
use Kreetancraft\PaymentGateway\Gateways\HimalayanBankGateway;
use Kreetancraft\PaymentGateway\Jobs\ReverifyPaymentJob;
use Kreetancraft\PaymentGateway\Models\Gateway;
use Kreetancraft\PaymentGateway\Models\Payment;
use Kreetancraft\PaymentGateway\Support\HblClient;
use Kreetancraft\PaymentGateway\Tests\Fixtures\Models\TestInvoice;

uses(RefreshDatabase::class);

/**
 * The buyer leaves for the bank's page. What happens if they never come back
 * and the notification is dropped?
 */
it('is queued when a checkout redirects away', function (): void {
    Queue::fake();

    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('supportsCurrency')->andReturn(true);
    $gateway->shouldReceive('charge')->andReturn(
        PaymentResult::success(orderReference: 'ORD-1', redirectUrl: 'https://bank.example/pay', checkoutData: null)
    );

    $resolver = Mockery::mock(GatewayResolver::class);
    $resolver->shouldReceive('getDefaultDriver')->andReturn('himalayan');
    $resolver->shouldReceive('resolve')->andReturn($gateway);
    app()->instance(GatewayResolver::class, $resolver);

    $invoice = TestInvoice::create(['number' => 'INV-1', 'currency' => 'NPR', 'total_cents' => 1000]);

    ChargePaymentAction::run([
        'payable_type' => 'invoice',
        'payable_id' => $invoice->id,
    ]);

    Queue::assertPushed(ReverifyPaymentJob::class);
});

it('is not queued when the payment settled without leaving', function (): void {
    Queue::fake();

    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('supportsCurrency')->andReturn(true);
    $gateway->shouldReceive('charge')->andReturn(
        PaymentResult::success(orderReference: 'ORD-2', redirectUrl: null, checkoutData: null)
    );

    $resolver = Mockery::mock(GatewayResolver::class);
    $resolver->shouldReceive('getDefaultDriver')->andReturn('stripe');
    $resolver->shouldReceive('resolve')->andReturn($gateway);
    app()->instance(GatewayResolver::class, $resolver);

    $invoice = TestInvoice::create(['number' => 'INV-2', 'currency' => 'USD', 'total_cents' => 1000]);

    ChargePaymentAction::run([
        'payable_type' => 'invoice',
        'payable_id' => $invoice->id,
    ]);

    Queue::assertNotPushed(ReverifyPaymentJob::class);
});

it('settles a payment whose callback never arrived', function (): void {
    Gateway::create([
        'code' => 'himalayan',
        'label' => 'HBL',
        'enabled' => true,
        'class' => HimalayanBankGateway::class,
        'currencies' => ['NPR'],
        'credentials' => ['office_id' => 'o', 'api_key' => 'k', 'encryption_key_id' => 'kid'],
    ]);

    $client = Mockery::mock(HblClient::class);
    $client->shouldReceive('transactionList')->andReturn(['response' => ['Data' => [
        ['PaymentStatusInfo' => ['PaymentStatus' => 'S'], 'amount' => 10.0, 'currencyCode' => 'NPR'],
    ]]]);
    app()->instance(HblClient::class, $client);

    $payment = Payment::factory()->create([
        'gateway' => 'himalayan',
        'gateway_reference' => 'ORD-LOST',
        'status' => PaymentStatus::Pending,
    ]);

    (new ReverifyPaymentJob($payment->id))->handle();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded);
});

it('does nothing for a payment that already reached a decision', function (): void {
    // Asking again would be a wasted call to the bank, and worse, a chance to
    // write over a settled record.
    $client = Mockery::mock(HblClient::class);
    $client->shouldReceive('transactionList')->never();
    app()->instance(HblClient::class, $client);

    $payment = Payment::factory()->create([
        'gateway' => 'himalayan',
        'gateway_reference' => 'ORD-DONE',
        'status' => PaymentStatus::Succeeded,
    ]);

    (new ReverifyPaymentJob($payment->id))->handle();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded);
});

it('does nothing for a payment that has vanished', function (): void {
    (new ReverifyPaymentJob(999999))->handle();
})->throwsNoExceptions();

it('backs off at two, five and ten minutes', function (): void {
    expect((new ReverifyPaymentJob(1))->backoff)->toBe([120, 300, 600])
        ->and((new ReverifyPaymentJob(1))->tries)->toBe(4);
});
