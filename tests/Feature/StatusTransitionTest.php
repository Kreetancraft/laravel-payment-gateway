<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Kreetancraft\PaymentGateway\Actions\VerifyPaymentAction;
use Kreetancraft\PaymentGateway\Contracts\GatewayResolver;
use Kreetancraft\PaymentGateway\Contracts\PaymentGateway;
use Kreetancraft\PaymentGateway\Data\VerificationResult;
use Kreetancraft\PaymentGateway\Enums\PaymentStatus;
use Kreetancraft\PaymentGateway\Events\PaymentSucceeded;
use Kreetancraft\PaymentGateway\Models\Payment;

uses(RefreshDatabase::class);

/**
 * Money travels one way.
 *
 * Four things settle a payment — the gateway's webhook, the buyer's return page,
 * the re-verify job and the reconcile sweep — and any two can arrive together.
 * Every one of them used to write the status with a bare save, so a message
 * repeating an older answer could undo a newer one.
 */
it('will not move a settled payment backwards', function (PaymentStatus $to): void {
    $payment = Payment::factory()->create(['status' => PaymentStatus::Succeeded, 'paid_at' => now()]);

    expect($payment->settleTo($to))->toBeFalse()
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Succeeded);
})->with([
    'back to pending' => PaymentStatus::Pending,
    'to failed' => PaymentStatus::Failed,
    'to cancelled' => PaymentStatus::Canceled,
]);

it('will not turn a refunded payment back into a paid one', function (): void {
    // The one that costs real money: a webhook redelivered after a refund mapped
    // to `succeeded`, moved the status, and fired fulfilment a second time on
    // money that had already been given back.
    Event::fake([PaymentSucceeded::class]);

    $payment = Payment::factory()->create(['status' => PaymentStatus::Refunded]);

    expect($payment->settleTo(PaymentStatus::Succeeded))->toBeFalse()
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Refunded);

    Event::assertNotDispatched(PaymentSucceeded::class);
});

it('lets a pending payment reach any first answer', function (PaymentStatus $to): void {
    $payment = Payment::factory()->create(['status' => PaymentStatus::Pending]);

    expect($payment->settleTo($to))->toBeTrue()
        ->and($payment->fresh()->status)->toBe($to);
})->with([
    'succeeded' => PaymentStatus::Succeeded,
    'failed' => PaymentStatus::Failed,
    'cancelled' => PaymentStatus::Canceled,
    'awaiting authentication' => PaymentStatus::RequiresAction,
]);

it('lets a payment awaiting authentication finish', function (): void {
    // 3-D Secure leaves the payment here. Nothing used to move it out again, so
    // it was neither settled nor reconciled.
    $payment = Payment::factory()->create(['status' => PaymentStatus::RequiresAction]);

    expect($payment->settleTo(PaymentStatus::Succeeded))->toBeTrue()
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Succeeded);
});

it('settles exactly once when two deliveries arrive together', function (): void {
    // The interleaving that produced two invoices: both readers see `pending`,
    // both write `succeeded`, and the event fires twice.
    Event::fake([PaymentSucceeded::class]);

    $payment = Payment::factory()->create(['status' => PaymentStatus::Pending]);

    $webhook = Payment::find($payment->id);
    $returnPage = Payment::find($payment->id);

    $first = $webhook->settleTo(PaymentStatus::Succeeded);
    $second = $returnPage->settleTo(PaymentStatus::Succeeded);

    expect($first)->toBeTrue()
        ->and($second)->toBeFalse();

    Event::assertDispatchedTimes(PaymentSucceeded::class, 1);
});

it('stamps paid_at when it settles, and only then', function (): void {
    $payment = Payment::factory()->create(['status' => PaymentStatus::Pending, 'paid_at' => null]);

    expect($payment->fresh()->paid_at)->toBeNull();

    $payment->settleTo(PaymentStatus::Succeeded);

    expect($payment->fresh()->paid_at)->not->toBeNull();
});

/**
 * A public GET could rewrite somebody else's money.
 */
it('asks the gateway that took the payment, whatever the url says', function (): void {
    // `/payment/success` is public. Anyone with a reference could add
    // `?gateway=stripe` to an HBL payment, have Stripe fail to recognise the id,
    // and turn a settled payment into a failed one.
    $payment = Payment::factory()->create([
        'gateway' => 'himalayan',
        'gateway_reference' => 'ORD-OWNED',
        'status' => PaymentStatus::Succeeded,
        'paid_at' => now(),
    ]);

    $himalayan = Mockery::mock(PaymentGateway::class);
    $himalayan->shouldReceive('verify')->andReturn(
        VerificationResult::success(transactionId: 'ORD-OWNED', status: 'succeeded', amount: 5.0, currency: 'USD')
    );

    $stripe = Mockery::mock(PaymentGateway::class);
    $stripe->shouldReceive('verify')->never();

    $resolver = Mockery::mock(GatewayResolver::class);
    $resolver->shouldReceive('resolve')->with('himalayan')->andReturn($himalayan);
    $resolver->shouldReceive('resolve')->with('stripe')->andReturn($stripe);
    $resolver->shouldReceive('getDefaultDriver')->andReturn('stripe');
    app()->instance(GatewayResolver::class, $resolver);

    VerifyPaymentAction::run(['reference' => 'ORD-OWNED', 'gateway' => 'stripe']);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded);
});

it('does not write off a payment because the gateway was unreachable', function (): void {
    // A DNS blip or TLS timeout while the buyer was returning used to be recorded
    // as a decline. Not getting an answer is not an answer.
    $payment = Payment::factory()->create([
        'gateway' => 'stripe',
        'gateway_reference' => 'cs_unreachable',
        'status' => PaymentStatus::Pending,
    ]);

    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('verify')->andReturn(
        VerificationResult::undetermined('cs_unreachable', 'Could not connect to Stripe')
    );

    $resolver = Mockery::mock(GatewayResolver::class);
    $resolver->shouldReceive('resolve')->andReturn($gateway);
    $resolver->shouldReceive('getDefaultDriver')->andReturn('stripe');
    app()->instance(GatewayResolver::class, $resolver);

    VerifyPaymentAction::run(['reference' => 'cs_unreachable']);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});
