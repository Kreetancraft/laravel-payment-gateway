<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Kreetancraft\PaymentGateway\Enums\PaymentStatus;
use Kreetancraft\PaymentGateway\Events\PaymentFailed;
use Kreetancraft\PaymentGateway\Events\PaymentSucceeded;
use Kreetancraft\PaymentGateway\Models\Payment;

uses(RefreshDatabase::class);

/**
 * The hook for "charge first, then create the invoice".
 *
 * The invoice is not what is being paid for — it is a document produced because
 * a payment succeeded. So it cannot be the Payable; the Payable is whatever
 * exists before the money does (a booking, an order, a cart), which is what
 * supplies the amount server-side. The invoice hangs off this event.
 *
 * Which makes "exactly once" the property that matters. Four paths settle a
 * payment — the webhook, a manual verify, the reconcile sweep and the re-verify
 * job — and Stripe will happily deliver the same webhook twice. If the event
 * fired per save rather than per transition, a buyer would get two invoices.
 */
it('fires when a payment becomes succeeded', function (): void {
    Event::fake([PaymentSucceeded::class]);

    $payment = Payment::factory()->create(['status' => PaymentStatus::Pending]);
    $payment->update(['status' => PaymentStatus::Succeeded]);

    Event::assertDispatched(PaymentSucceeded::class, fn (PaymentSucceeded $e): bool => $e->payment->is($payment));
});

it('does not fire again when the same webhook arrives twice', function (): void {
    // The one that would produce a duplicate invoice.
    Event::fake([PaymentSucceeded::class]);

    $payment = Payment::factory()->create(['status' => PaymentStatus::Pending]);
    $payment->update(['status' => PaymentStatus::Succeeded]);
    $payment->update(['status' => PaymentStatus::Succeeded]);
    $payment->update(['paid_at' => now()]);

    Event::assertDispatchedTimes(PaymentSucceeded::class, 1);
});

it('does not fire while the payment is still pending', function (): void {
    Event::fake([PaymentSucceeded::class]);

    $payment = Payment::factory()->create(['status' => PaymentStatus::Pending]);
    $payment->update(['gateway_reference' => 'cs_test_1']);

    Event::assertNotDispatched(PaymentSucceeded::class);
});

it('does not fire on the row that starts out succeeded', function (): void {
    // A payment created already settled has not transitioned, and firing here
    // would double up with whatever created it.
    Event::fake([PaymentSucceeded::class]);

    Payment::factory()->create(['status' => PaymentStatus::Succeeded]);

    Event::assertNotDispatched(PaymentSucceeded::class);
});

it('fires the failure event on a decline', function (): void {
    Event::fake([PaymentFailed::class]);

    $payment = Payment::factory()->create(['status' => PaymentStatus::Pending]);
    $payment->update(['status' => PaymentStatus::Failed]);

    Event::assertDispatched(PaymentFailed::class);
});

it('fires the failure event when a session is cancelled', function (): void {
    Event::fake([PaymentFailed::class]);

    $payment = Payment::factory()->create(['status' => PaymentStatus::Pending]);
    $payment->update(['status' => PaymentStatus::Canceled]);

    Event::assertDispatched(PaymentFailed::class);
});

it('fires no matter which path settled the payment', function (): void {
    // The reason this lives on the model rather than in the webhook handler.
    Event::fake([PaymentSucceeded::class]);

    $viaWebhook = Payment::factory()->create(['status' => PaymentStatus::Pending]);
    $viaSweep = Payment::factory()->create(['status' => PaymentStatus::Pending]);

    $viaWebhook->status = PaymentStatus::Succeeded;
    $viaWebhook->save();

    $viaSweep->forceFill(['status' => PaymentStatus::Succeeded])->save();

    Event::assertDispatchedTimes(PaymentSucceeded::class, 2);
});
