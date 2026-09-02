<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreetancraft\PaymentGateway\Actions\ChargePaymentAction;
use Kreetancraft\PaymentGateway\Contracts\GatewayResolver;
use Kreetancraft\PaymentGateway\Contracts\PaymentGateway;
use Kreetancraft\PaymentGateway\Data\PaymentResult;
use Kreetancraft\PaymentGateway\Data\VerificationResult;
use Kreetancraft\PaymentGateway\Enums\PaymentStatus;
use Kreetancraft\PaymentGateway\Models\Coupon;
use Kreetancraft\PaymentGateway\Models\Payment;
use Kreetancraft\PaymentGateway\Tests\Fixtures\Models\TestInvoice;

uses(RefreshDatabase::class);

/**
 * Where the amount comes from.
 *
 * `POST /checkout` is public, and ChargePaymentAction used to validate
 * `amount_cents` as `required|integer|min:1` — so the buyer chose the price.
 * Payment had no link to what was being bought, so there was nothing to check
 * an amount against even in principle. The monolith reads it off the invoice
 * server-side and lets the request choose only *which* server-computed amount;
 * this is the same idea behind a contract.
 */
function fakeGatewayAccepting(?callable $inspect = null): void
{
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('supportsCurrency')->andReturn(true);
    $gateway->shouldReceive('charge')->andReturnUsing(function (array $data) use ($inspect): PaymentResult {
        if ($inspect !== null) {
            $inspect($data);
        }

        return PaymentResult::success(orderReference: 'ref_1', redirectUrl: null, checkoutData: null);
    });

    $resolver = Mockery::mock(GatewayResolver::class);
    $resolver->shouldReceive('getDefaultDriver')->andReturn('stripe');
    $resolver->shouldReceive('resolve')->andReturn($gateway);
    app()->instance(GatewayResolver::class, $resolver);
}

it('ignores an amount supplied by the caller', function (): void {
    $seen = null;
    fakeGatewayAccepting(function (array $data) use (&$seen): void {
        $seen = $data['amount_cents'];
    });

    $invoice = TestInvoice::create(['number' => 'INV-1', 'currency' => 'USD', 'total_cents' => 50000]);

    ChargePaymentAction::run([
        'payable_type' => 'invoice',
        'payable_id' => $invoice->id,
        'amount_cents' => 1,          // the whole point
        'currency' => 'XXX',
    ]);

    expect($seen)->toBe(50000)
        ->and(Payment::first()->amount_cents)->toBe(50000)
        ->and(Payment::first()->currency)->toBe('USD');
});

it('charges only what is still outstanding', function (): void {
    fakeGatewayAccepting();

    $invoice = TestInvoice::create([
        'number' => 'INV-2', 'currency' => 'USD', 'total_cents' => 50000, 'paid_cents' => 30000,
    ]);

    ChargePaymentAction::run(['payable_type' => 'invoice', 'payable_id' => $invoice->id]);

    expect(Payment::first()->amount_cents)->toBe(20000);
});

it('refuses a payable that is already settled', function (): void {
    fakeGatewayAccepting();

    $invoice = TestInvoice::create([
        'number' => 'INV-3', 'currency' => 'USD', 'total_cents' => 50000, 'paid_cents' => 50000,
    ]);

    $result = ChargePaymentAction::run(['payable_type' => 'invoice', 'payable_id' => $invoice->id]);

    expect($result->success)->toBeFalse()
        ->and($result->errorCode)->toBe('nothing_to_pay')
        ->and(Payment::count())->toBe(0);
});

it('refuses a payable type that is not on the allowlist', function (): void {
    // Without the allowlist a caller could point checkout at any model in the
    // application and have it charged.
    fakeGatewayAccepting();

    $result = ChargePaymentAction::run([
        'payable_type' => 'user',
        'payable_id' => 1,
    ]);

    expect($result->success)->toBeFalse()
        ->and($result->errorCode)->toBe('payable_not_found');
});

it('refuses a payable that does not exist', function (): void {
    fakeGatewayAccepting();

    $result = ChargePaymentAction::run(['payable_type' => 'invoice', 'payable_id' => 9999]);

    expect($result->errorCode)->toBe('payable_not_found');
});

it('records what the payment is for', function (): void {
    fakeGatewayAccepting();

    $invoice = TestInvoice::create(['number' => 'INV-4', 'currency' => 'USD', 'total_cents' => 1000]);

    ChargePaymentAction::run(['payable_type' => 'invoice', 'payable_id' => $invoice->id]);

    $payment = Payment::first();

    expect($payment->payable_id)->toBe($invoice->id)
        ->and($payment->payable)->toBeInstanceOf(TestInvoice::class);
});

it('writes the payment row before calling the gateway', function (): void {
    // It used to charge first and create the row after, so a crash in between
    // took the buyer's money with nothing recorded locally.
    $existedDuringCharge = false;

    fakeGatewayAccepting(function () use (&$existedDuringCharge): void {
        $existedDuringCharge = Payment::query()->exists();
    });

    $invoice = TestInvoice::create(['number' => 'INV-5', 'currency' => 'USD', 'total_cents' => 1000]);

    ChargePaymentAction::run(['payable_type' => 'invoice', 'payable_id' => $invoice->id]);

    expect($existedDuringCharge)->toBeTrue();
});

it('does not create a second payment for the same payable', function (): void {
    fakeGatewayAccepting();

    $invoice = TestInvoice::create(['number' => 'INV-6', 'currency' => 'USD', 'total_cents' => 1000]);

    ChargePaymentAction::run(['payable_type' => 'invoice', 'payable_id' => $invoice->id]);
    ChargePaymentAction::run(['payable_type' => 'invoice', 'payable_id' => $invoice->id]);

    expect(Payment::count())->toBe(1);
});

it('keys idempotency on the payable, not on the request body', function (): void {
    // The old key was a hash of the whole payload, so two buyers paying the same
    // amount for the same thing with identical payloads collided — and adding
    // any field defeated it entirely.
    fakeGatewayAccepting();

    $invoice = TestInvoice::create(['number' => 'INV-7', 'currency' => 'USD', 'total_cents' => 1000]);

    ChargePaymentAction::run(['payable_type' => 'invoice', 'payable_id' => $invoice->id]);
    ChargePaymentAction::run([
        'payable_type' => 'invoice',
        'payable_id' => $invoice->id,
        'customer_name' => 'a field that was not there before',
    ]);

    expect(Payment::count())->toBe(1);

    // A different invoice is a different payment, even at the same price.
    $other = TestInvoice::create(['number' => 'INV-8', 'currency' => 'USD', 'total_cents' => 1000]);
    ChargePaymentAction::run(['payable_type' => 'invoice', 'payable_id' => $other->id]);

    expect(Payment::count())->toBe(2);
});

it('marks a failed charge failed without losing the record', function (): void {
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('supportsCurrency')->andReturn(true);
    $gateway->shouldReceive('charge')->andReturn(
        PaymentResult::failure(orderReference: '', errorMessage: 'declined', errorCode: 'card_declined')
    );

    $resolver = Mockery::mock(GatewayResolver::class);
    $resolver->shouldReceive('getDefaultDriver')->andReturn('stripe');
    $resolver->shouldReceive('resolve')->andReturn($gateway);
    app()->instance(GatewayResolver::class, $resolver);

    $invoice = TestInvoice::create(['number' => 'INV-9', 'currency' => 'USD', 'total_cents' => 1000]);

    ChargePaymentAction::run(['payable_type' => 'invoice', 'payable_id' => $invoice->id]);

    expect(Payment::first()->status)->toBe(PaymentStatus::Failed)
        ->and(Payment::first()->payable_id)->toBe($invoice->id);
});

it('lets a buyer try again after a decline', function (): void {
    // Found on the bench, against the real gateway: a declined card left a
    // `failed` payment, the idempotency check matched it whatever its status,
    // and the next attempt was swallowed — the buyer was sent to the success
    // page holding the reference of the payment that had just failed.
    $declining = Mockery::mock(PaymentGateway::class);
    $declining->shouldReceive('supportsCurrency')->andReturn(true);
    $declining->shouldReceive('charge')->andReturn(
        PaymentResult::failure(orderReference: 'ORD-A', errorMessage: 'declined', errorCode: 'card_declined')
    );

    $resolver = Mockery::mock(GatewayResolver::class);
    $resolver->shouldReceive('getDefaultDriver')->andReturn('stripe');
    $resolver->shouldReceive('resolve')->andReturn($declining);
    app()->instance(GatewayResolver::class, $resolver);

    $invoice = TestInvoice::create(['number' => 'INV-R', 'currency' => 'USD', 'total_cents' => 5000]);

    ChargePaymentAction::run(['payable_type' => 'invoice', 'payable_id' => $invoice->id]);
    expect(Payment::count())->toBe(1);

    // Second go: a new attempt, not the old one handed back.
    $result = ChargePaymentAction::run(['payable_type' => 'invoice', 'payable_id' => $invoice->id]);

    expect(Payment::count())->toBe(2)
        ->and($result->success)->toBeFalse();
});

it('still refuses a second attempt while one is in flight', function (): void {
    // A redirect-style gateway leaves the payment pending while the buyer is on
    // the bank's page. Starting another one there would be a double charge.
    fakeGatewayAccepting();

    $redirecting = Mockery::mock(PaymentGateway::class);
    $redirecting->shouldReceive('supportsCurrency')->andReturn(true);
    $redirecting->shouldReceive('charge')->andReturn(
        PaymentResult::success(orderReference: 'ORD-B', redirectUrl: 'https://bank.example/pay', checkoutData: null)
    );

    $resolver = Mockery::mock(GatewayResolver::class);
    $resolver->shouldReceive('getDefaultDriver')->andReturn('himalayan');
    $resolver->shouldReceive('resolve')->andReturn($redirecting);
    app()->instance(GatewayResolver::class, $resolver);

    $invoice = TestInvoice::create(['number' => 'INV-P', 'currency' => 'NPR', 'total_cents' => 5000]);

    ChargePaymentAction::run(['payable_type' => 'invoice', 'payable_id' => $invoice->id]);
    ChargePaymentAction::run(['payable_type' => 'invoice', 'payable_id' => $invoice->id]);

    expect(Payment::count())->toBe(1);
});

it('gives the gateway a key that changes with the amount', function (): void {
    // Found on the bench, against real Stripe: the key handed to the gateway was
    // "charge:" plus the payable reference, which is the invoice number and
    // never changes. Applying a coupon and paying sent the first request's key
    // with a smaller amount, and Stripe refused outright —
    //   "Keys for idempotent requests can only be used with the same parameters
    //    they were first used with."
    // The buyer could not pay at all once they used a discount.
    $keys = [];
    fakeGatewayAccepting(function (array $data) use (&$keys): void {
        $keys[] = $data['idempotency_key'] ?? null;
    });

    Coupon::create([
        'code' => 'TENOFF', 'name' => 'Ten off', 'type' => 'fixed',
        'value' => 100, 'is_active' => true,
    ]);

    $full = TestInvoice::create(['number' => 'INV-K1', 'currency' => 'USD', 'total_cents' => 50000]);
    $discounted = TestInvoice::create(['number' => 'INV-K2', 'currency' => 'USD', 'total_cents' => 50000]);

    ChargePaymentAction::run(['payable_type' => 'invoice', 'payable_id' => $full->id]);
    ChargePaymentAction::run(['payable_type' => 'invoice', 'payable_id' => $discounted->id, 'coupon' => 'TENOFF']);

    expect($keys)->toHaveCount(2)
        ->and($keys[0])->not->toBeNull()
        ->and($keys[0])->not->toBe($keys[1]);
});

it('still sends the same key for a genuine double submit', function (): void {
    // The other half: two identical clicks must collapse, or the buyer is
    // charged twice.
    $keys = [];
    fakeGatewayAccepting(function (array $data) use (&$keys): void {
        $keys[] = $data['idempotency_key'] ?? null;
    });

    $invoice = TestInvoice::create(['number' => 'INV-K3', 'currency' => 'USD', 'total_cents' => 2500]);

    ChargePaymentAction::run(['payable_type' => 'invoice', 'payable_id' => $invoice->id]);
    ChargePaymentAction::run(['payable_type' => 'invoice', 'payable_id' => $invoice->id]);

    // The second never reaches the gateway — the in-flight guard stops it.
    expect(Payment::count())->toBe(1)
        ->and($keys)->toHaveCount(1);
});

it('sends a returning buyer back to the page they left', function (): void {
    // Reported from the bench: start a payment, come back to checkout, and the
    // screen said "the payment was started but has not completed" with no
    // redirect and no way forward. The in-flight guard returned success with a
    // null redirect URL, so the abandoned attempt blocked every later one.
    $redirecting = Mockery::mock(PaymentGateway::class);
    $redirecting->shouldReceive('supportsCurrency')->andReturn(true);
    $redirecting->shouldReceive('charge')->once()->andReturn(
        PaymentResult::success(orderReference: 'cs_1', redirectUrl: 'https://checkout.example/session/cs_1')
    );

    $resolver = Mockery::mock(GatewayResolver::class);
    $resolver->shouldReceive('getDefaultDriver')->andReturn('stripe');
    $resolver->shouldReceive('resolve')->andReturn($redirecting);
    app()->instance(GatewayResolver::class, $resolver);

    $invoice = TestInvoice::create(['number' => 'INV-RS', 'currency' => 'USD', 'total_cents' => 5000]);

    ChargePaymentAction::run(['payable_type' => 'invoice', 'payable_id' => $invoice->id]);

    // Second attempt: no new charge, but the buyer gets the same page back.
    $again = ChargePaymentAction::run(['payable_type' => 'invoice', 'payable_id' => $invoice->id]);

    expect(Payment::count())->toBe(1)
        ->and($again->success)->toBeTrue()
        ->and($again->redirectUrl)->toBe('https://checkout.example/session/cs_1');
});

it('sends a buyer who already paid to the success page', function (): void {
    // The other half: they paid, came back to checkout, and were told to wait.
    fakeGatewayAccepting();

    $invoice = TestInvoice::create(['number' => 'INV-PD', 'currency' => 'USD', 'total_cents' => 5000]);

    ChargePaymentAction::run(['payable_type' => 'invoice', 'payable_id' => $invoice->id]);
    Payment::first()->update(['status' => PaymentStatus::Succeeded, 'paid_at' => now()]);

    $again = ChargePaymentAction::run(['payable_type' => 'invoice', 'payable_id' => $invoice->id]);

    expect($again->settled)->toBeTrue()
        ->and(Payment::count())->toBe(1);
});

it('lets a buyer start again once an abandoned attempt is confirmed unpaid', function (): void {
    // Seen on the bench: a pending attempt with no stored hosted-page URL — a row
    // written before resume existed, or a gateway that gave none — blocked the
    // payable forever. The screen said the payment had started but not
    // completed, with no way forward and no way to start again.
    $invoice = TestInvoice::create(['number' => 'INV-AB', 'currency' => 'USD', 'total_cents' => 500]);

    $stale = Payment::factory()->create([
        'gateway' => 'stripe',
        'gateway_reference' => 'cs_abandoned',
        'status' => PaymentStatus::Pending,
        'amount_cents' => 500,
        'currency' => 'USD',
        'payable_type' => $invoice->getMorphClass(),
        'payable_id' => $invoice->id,
        'created_at' => now()->subHour(),
        'metadata' => [],
    ]);
    // Give it the key the action will compute, so it is genuinely in the way.
    $stale->update(['idempotency_key' => hash('sha256', implode(':', [
        $invoice->getMorphClass(), (string) $invoice->id, $invoice->paymentReference(),
        'stripe', '500', 'USD', '0',
    ]))]);

    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('supportsCurrency')->andReturn(true);
    // The bank says it was never paid.
    $gateway->shouldReceive('verify')->andReturn(
        VerificationResult::failure('cs_abandoned', 'Not paid')
    );
    $gateway->shouldReceive('charge')->once()->andReturn(
        PaymentResult::success(orderReference: 'cs_new', redirectUrl: 'https://checkout.example/new')
    );

    $resolver = Mockery::mock(GatewayResolver::class);
    $resolver->shouldReceive('getDefaultDriver')->andReturn('stripe');
    $resolver->shouldReceive('resolve')->andReturn($gateway);
    app()->instance(GatewayResolver::class, $resolver);

    $result = ChargePaymentAction::run(['payable_type' => 'invoice', 'payable_id' => $invoice->id]);

    expect($result->redirectUrl)->toBe('https://checkout.example/new')
        ->and(Payment::count())->toBe(2);
});

it('does not start a second attempt while the first is genuinely recent', function (): void {
    // The other side of it: a buyer double-clicking must not open two sessions.
    $invoice = TestInvoice::create(['number' => 'INV-RC', 'currency' => 'USD', 'total_cents' => 500]);

    $recent = Payment::factory()->create([
        'gateway' => 'stripe',
        'gateway_reference' => 'cs_recent',
        'status' => PaymentStatus::Pending,
        'amount_cents' => 500,
        'currency' => 'USD',
        'payable_type' => $invoice->getMorphClass(),
        'payable_id' => $invoice->id,
        'created_at' => now(),
        'metadata' => [],
    ]);
    $recent->update(['idempotency_key' => hash('sha256', implode(':', [
        $invoice->getMorphClass(), (string) $invoice->id, $invoice->paymentReference(),
        'stripe', '500', 'USD', '0',
    ]))]);

    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('supportsCurrency')->andReturn(true);
    $gateway->shouldReceive('verify')->andReturn(
        VerificationResult::undetermined('cs_recent', 'Still pending')
    );
    $gateway->shouldReceive('charge')->never();

    $resolver = Mockery::mock(GatewayResolver::class);
    $resolver->shouldReceive('getDefaultDriver')->andReturn('stripe');
    $resolver->shouldReceive('resolve')->andReturn($gateway);
    app()->instance(GatewayResolver::class, $resolver);

    ChargePaymentAction::run(['payable_type' => 'invoice', 'payable_id' => $invoice->id]);

    expect(Payment::count())->toBe(1);
});

it('sends the buyer to success if the abandoned attempt turns out to be paid', function (): void {
    // The dangerous case. They paid, we never heard, and they came back to pay
    // again — asking the gateway first is what stops the second charge.
    $invoice = TestInvoice::create(['number' => 'INV-PA', 'currency' => 'USD', 'total_cents' => 500]);

    $stale = Payment::factory()->create([
        'gateway' => 'stripe',
        'gateway_reference' => 'cs_actually_paid',
        'status' => PaymentStatus::Pending,
        'amount_cents' => 500,
        'currency' => 'USD',
        'payable_type' => $invoice->getMorphClass(),
        'payable_id' => $invoice->id,
        'created_at' => now()->subHour(),
        'metadata' => [],
    ]);
    $stale->update(['idempotency_key' => hash('sha256', implode(':', [
        $invoice->getMorphClass(), (string) $invoice->id, $invoice->paymentReference(),
        'stripe', '500', 'USD', '0',
    ]))]);

    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('supportsCurrency')->andReturn(true);
    $gateway->shouldReceive('verify')->andReturn(
        VerificationResult::success(
            transactionId: 'cs_actually_paid', status: 'succeeded', amount: 5.0, currency: 'USD'
        )
    );
    $gateway->shouldReceive('charge')->never();

    $resolver = Mockery::mock(GatewayResolver::class);
    $resolver->shouldReceive('getDefaultDriver')->andReturn('stripe');
    $resolver->shouldReceive('resolve')->andReturn($gateway);
    app()->instance(GatewayResolver::class, $resolver);

    $result = ChargePaymentAction::run(['payable_type' => 'invoice', 'payable_id' => $invoice->id]);

    expect($result->settled)->toBeTrue()
        ->and(Payment::count())->toBe(1);
});

it('lets a buyer pay after an old attempt was deleted', function (): void {
    // Payment soft-deletes, and `idempotency_key` is unique across every row
    // including deleted ones — but the in-flight guard only queries live rows.
    // So a deleted payment kept its key in the index invisibly, and the next
    // attempt at the same amount died on a raw UniqueConstraintViolation the
    // buyer could do nothing about.
    fakeGatewayAccepting();

    $invoice = TestInvoice::create(['number' => 'INV-DEL', 'currency' => 'USD', 'total_cents' => 500]);

    ChargePaymentAction::run(['payable_type' => 'invoice', 'payable_id' => $invoice->id]);
    Payment::first()->delete();

    $result = ChargePaymentAction::run(['payable_type' => 'invoice', 'payable_id' => $invoice->id]);

    expect($result->success)->toBeTrue()
        ->and(Payment::count())->toBe(1);
});
