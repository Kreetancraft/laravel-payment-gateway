<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreetancraft\PaymentGateway\Enums\PaymentStatus;
use Kreetancraft\PaymentGateway\Gateways\HimalayanBankGateway;
use Kreetancraft\PaymentGateway\Models\Gateway;
use Kreetancraft\PaymentGateway\Models\Payment;
use Kreetancraft\PaymentGateway\Support\HblClient;

uses(RefreshDatabase::class);

/**
 * The sweep for payments nobody ever told us the outcome of.
 *
 * A dropped callback is the worst failure this package has: money taken, and an
 * order that never completes, with nothing in the application that knows to
 * look. The monolith runs the same idea as `hbl:reconcile-stale`.
 */
function enabledHblGateway(): Gateway
{
    return Gateway::create([
        'code' => 'himalayan',
        'label' => 'Himalayan Bank',
        'enabled' => true,
        'class' => HimalayanBankGateway::class,
        'currencies' => ['NPR'],
        'credentials' => [
            'office_id' => 'DEMOOFFICE',
            'api_key' => 'k',
            'encryption_key_id' => 'kid',
        ],
    ]);
}

/** @param array<string, mixed> $tx */
function hblAnswers(array $tx): void
{
    $client = Mockery::mock(HblClient::class);
    $client->shouldReceive('transactionList')->andReturn(['response' => ['Data' => [$tx]]]);
    app()->instance(HblClient::class, $client);
}

it('settles a payment the gateway says was paid', function (): void {
    enabledHblGateway();
    hblAnswers(['PaymentStatusInfo' => ['PaymentStatus' => 'S'], 'amount' => 100.0, 'currencyCode' => 'NPR']);

    $payment = Payment::factory()->create([
        'gateway' => 'himalayan',
        'gateway_reference' => 'ORD-STALE',
        'status' => PaymentStatus::Pending,
        'created_at' => now()->subHour(),
    ]);

    $this->artisan('payment-gateway:reconcile')->assertSuccessful();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded);
});

it('leaves a payment that is still in flight alone', function (): void {
    enabledHblGateway();
    hblAnswers(['PaymentStatusInfo' => ['PaymentStatus' => 'I'], 'amount' => 100.0, 'currencyCode' => 'NPR']);

    $payment = Payment::factory()->create([
        'gateway' => 'himalayan',
        'gateway_reference' => 'ORD-INFLIGHT',
        'status' => PaymentStatus::Pending,
        'created_at' => now()->subHour(),
    ]);

    $this->artisan('payment-gateway:reconcile')->assertSuccessful();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('does not chase a payment that was only just created', function (): void {
    // Asking about a payment seconds old races the buyer's own return from the
    // payment page.
    enabledHblGateway();

    $client = Mockery::mock(HblClient::class);
    $client->shouldReceive('transactionList')->never();
    app()->instance(HblClient::class, $client);

    Payment::factory()->create([
        'gateway' => 'himalayan',
        'gateway_reference' => 'ORD-FRESH',
        'status' => PaymentStatus::Pending,
        'created_at' => now(),
    ]);

    $this->artisan('payment-gateway:reconcile')->assertSuccessful();
});

it('ignores payments that already reached a decision', function (): void {
    enabledHblGateway();

    $client = Mockery::mock(HblClient::class);
    $client->shouldReceive('transactionList')->never();
    app()->instance(HblClient::class, $client);

    Payment::factory()->create([
        'gateway' => 'himalayan',
        'gateway_reference' => 'ORD-DONE',
        'status' => PaymentStatus::Succeeded,
        'created_at' => now()->subDay(),
    ]);

    $this->artisan('payment-gateway:reconcile')->assertSuccessful();
});

it('keeps going when one gateway cannot be reached', function (): void {
    enabledHblGateway();

    $client = Mockery::mock(HblClient::class);
    $client->shouldReceive('transactionList')->andThrow(new RuntimeException('unreachable'));
    app()->instance(HblClient::class, $client);

    $payment = Payment::factory()->create([
        'gateway' => 'himalayan',
        'gateway_reference' => 'ORD-DOWN',
        'status' => PaymentStatus::Pending,
        'created_at' => now()->subHour(),
    ]);

    $this->artisan('payment-gateway:reconcile')->assertSuccessful();

    // Undetermined, so untouched — not written off.
    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('reports when a gateway is not ready', function (): void {
    Gateway::create([
        'code' => 'himalayan',
        'label' => 'Himalayan Bank',
        'enabled' => true,
        'class' => HimalayanBankGateway::class,
        'credentials' => ['office_id' => 'DEMOOFFICE'],   // no api key, no kid
    ]);

    $this->artisan('payment-gateway:status')->assertFailed();
});

it('reports success when every credential is present', function (): void {
    Gateway::create([
        'code' => 'himalayan',
        'label' => 'Himalayan Bank',
        'enabled' => true,
        'class' => HimalayanBankGateway::class,
        'credentials' => [
            'office_id' => 'o',
            'api_key' => 'k',
            'encryption_key_id' => 'kid',
            'merchant_signing_key' => 'x',
            'merchant_decryption_key' => 'x',
            'paco_encryption_public_key' => 'x',
            'paco_signing_public_key' => 'x',
        ],
    ]);

    $this->artisan('payment-gateway:status')->assertSuccessful();
});

it('fails when no gateway is enabled at all', function (): void {
    $this->artisan('payment-gateway:status')->assertFailed();
});
