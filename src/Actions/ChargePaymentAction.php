<?php

namespace Kreetancraft\PaymentGateway\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Kreetancraft\PaymentGateway\Contracts\GatewayResolver;
use Kreetancraft\PaymentGateway\Contracts\Payable;
use Kreetancraft\PaymentGateway\Contracts\SupportsDeposit;
use Kreetancraft\PaymentGateway\Data\PaymentResult;
use Kreetancraft\PaymentGateway\Enums\PaymentStatus;
use Kreetancraft\PaymentGateway\Jobs\ReverifyPaymentJob;
use Kreetancraft\PaymentGateway\Models\Coupon;
use Kreetancraft\PaymentGateway\Models\Payment;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class ChargePaymentAction
{
    use AsAction;

    public function __construct(
        protected GatewayResolver $resolver,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): PaymentResult
    {
        $validator = Validator::make($data, [
            // No amount and no currency. Both come off the payable — see the
            // Payable contract for why. A caller supplying `amount_cents` is
            // simply ignored rather than rejected, so an older client keeps
            // working; it just cannot choose the price any more.
            'payable_type' => ['required', 'string'],
            'payable_id' => ['required'],
            // A code, not a discount. The reduction is computed here from the
            // Coupon row, so the caller cannot choose how much to take off.
            'coupon' => ['sometimes', 'nullable', 'string', 'max:64'],
            'gateway' => ['sometimes', 'string'],
            // Which of two server-computed amounts, never what either is worth.
            'amount_type' => ['sometimes', 'nullable', 'in:full,balance,deposit'],
            'customer_email' => ['sometimes', 'nullable', 'email'],
            'customer_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return PaymentResult::failure(
                orderReference: (string) ($data['reference'] ?? $data['order_reference'] ?? ''),
                errorMessage: $validator->errors()->first() ?? 'Validation failed.',
                errorCode: 'validation_error'
            );
        }

        $validated = $validator->validated();

        $payable = $this->resolvePayable((string) $validated['payable_type'], $validated['payable_id']);

        if ($payable === null) {
            return PaymentResult::failure(
                orderReference: '',
                errorMessage: 'That is not something this application accepts payment for.',
                errorCode: 'payable_not_found'
            );
        }

        $currency = strtoupper($payable->paymentCurrency());
        $outstanding = $payable->paymentAmountCents();
        $amountType = (string) ($validated['amount_type'] ?? 'full');

        if ($amountType === 'deposit') {
            if (! $payable instanceof SupportsDeposit) {
                return PaymentResult::failure(
                    orderReference: $payable->paymentReference(),
                    errorMessage: 'This cannot be paid by deposit.',
                    errorCode: 'deposit_not_supported'
                );
            }

            // What is left *of the deposit*. Somebody who has paid part of it
            // owes the rest, not the whole figure again — and never more than
            // the invoice still has outstanding, or a deposit larger than the
            // remaining balance would overcharge on the last instalment.
            $alreadyPaid = Payment::netPaidCentsFor($payable, $currency);
            $amountCents = min($outstanding, max(0, $payable->paymentDepositCents() - $alreadyPaid));

            if ($amountCents <= 0) {
                return PaymentResult::failure(
                    orderReference: $payable->paymentReference(),
                    errorMessage: 'The deposit on this has already been paid.',
                    errorCode: 'deposit_already_paid'
                );
            }
        } else {
            $amountCents = $outstanding;
        }

        if ($amountCents <= 0) {
            return PaymentResult::failure(
                orderReference: $payable->paymentReference(),
                errorMessage: 'There is nothing left to pay on this.',
                errorCode: 'nothing_to_pay'
            );
        }
        $discountCents = 0;

        if (filled($data['coupon'] ?? null)) {
            $coupon = Coupon::query()->where('code', trim((string) $data['coupon']))->first();

            if ($coupon === null || ! $coupon->canApply(auth()->id(), $amountCents, $currency)) {
                return PaymentResult::failure(
                    orderReference: $payable->paymentReference(),
                    errorMessage: 'That coupon cannot be applied to this order.',
                    errorCode: 'coupon_invalid'
                );
            }

            $discountCents = max(0, $coupon->calculateDiscount($amountCents));
        }

        $amountCents -= $discountCents;

        if ($amountCents <= 0) {
            // A full discount is a legitimate thing to configure, but there is
            // no gateway call to make for it. The host settles it directly.
            return PaymentResult::failure(
                orderReference: $payable->paymentReference(),
                errorMessage: 'This order comes to nothing once the discount is applied; no payment is needed.',
                errorCode: 'nothing_to_pay'
            );
        }

        $gatewayCode = (string) ($data['gateway'] ?? $validated['gateway'] ?? $this->resolver->getDefaultDriver() ?? 'stripe');

        if (blank($gatewayCode)) {
            return PaymentResult::failure(
                orderReference: '',
                errorMessage: 'No gateway specified and no default gateway configured.',
                errorCode: 'gateway_missing'
            );
        }

        $gateway = $this->resolver->resolve($gatewayCode);

        if ($gateway === null) {
            return PaymentResult::failure(
                orderReference: '',
                errorMessage: "Gateway [{$gatewayCode}] is not configured or not enabled.",
                errorCode: 'gateway_not_found'
            );
        }

        if (! $gateway->supportsCurrency($currency)) {
            return PaymentResult::failure(
                orderReference: '',
                errorMessage: "Currency [{$currency}] is not supported by gateway [{$gatewayCode}].",
                errorCode: 'currency_not_supported'
            );
        }

        // Keyed on what is being bought, not on a hash of the whole request.
        // The old key was `hash(json_encode($data))`, so two buyers paying the
        // same amount for the same thing with identical payloads collided and
        // the second silently received the first's payment — while adding any
        // field at all defeated it.
        $idempotencyKey = $this->idempotencyKeyFor($payable, $gatewayCode, $amountCents, $currency);

        // Only an attempt that is still open, or already paid, stops another one.
        //
        // Returning the existing row whatever its status meant a buyer whose card
        // was declined could never try again — and worse, was sent to the success
        // page holding the reference of the payment that had just failed. A
        // failed or cancelled attempt has to leave the door open.
        $existing = Payment::query()
            ->where('idempotency_key', $idempotencyKey)
            ->whereIn('status', [PaymentStatus::Pending, PaymentStatus::Succeeded])
            ->first();

        if ($existing !== null) {
            $resolved = $this->resolveOpenAttempt($existing);

            if ($resolved !== null) {
                return $resolved;
            }

            // The old attempt turned out to be dead and has been closed. That
            // changes the attempt count, so the key has to be recomputed before
            // a new row is written with it.
            $idempotencyKey = $this->idempotencyKeyFor($payable, $gatewayCode, $amountCents, $currency);
        }

        $paymentData = [
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'gateway' => $gatewayCode,
            'idempotency_key' => $idempotencyKey,
            'payable_type' => $payable->getMorphClass(),
            'payable_id' => $payable->getKey(),
            'customer_email' => $data['customer_email'] ?? null,
            'customer_name' => $data['customer_name'] ?? null,
            'customer_phone' => $data['customer_phone'] ?? null,
            'customer_address' => $data['customer_address'] ?? null,
            'description' => $payable->paymentDescription() ?? ($data['description'] ?? null),
            'metadata' => array_filter([
                ...(array) ($data['metadata'] ?? []),
                'coupon' => $data['coupon'] ?? null,
                'discount_cents' => $discountCents ?: null,
            ], fn ($value): bool => $value !== null),
        ];

        // The row goes in before the gateway is called. It used to be the other
        // way round — charge, then create — so a crash in between took the
        // buyer's money with nothing recorded locally. The monolith writes its
        // transaction row before redirecting for the same reason.
        $payment = Payment::create([
            ...$paymentData,
            'status' => PaymentStatus::Pending,
        ]);

        $result = $gateway->charge([
            ...$this->withoutCallerControlledSettings($data),
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'reference_seed' => $payable->paymentReference(),
            // The payment's own reference, which exists because the row is
            // written before the charge. Gateways put this in the URLs the buyer
            // returns to, and it is what the success page looks the payment up
            // by. Without it `reference=` came back empty and the buyer who had
            // just paid was told the payment could not be verified.
            'order_reference' => $payment->reference,
            // The gateway should use the same key this action already computed,
            // rather than deriving its own from the payable reference — that one
            // is constant for the life of the invoice.
            'idempotency_key' => $idempotencyKey,
            'description' => $paymentData['description'],
        ]);

        if (! $result->success) {
            $payment->update([
                'gateway_reference' => $result->orderReference ?: null,
                'status' => PaymentStatus::Failed,
            ]);

            return $result;
        }

        // "No redirect URL" used to be read as "paid", which is not the same
        // thing at all: a gateway that accepts a charge and hands back a handle
        // has taken no money yet. That marked unpaid payments succeeded, stamped
        // paid_at on them, and — through the checkout screen's matching
        // assumption — showed the buyer a success page for a card they had never
        // entered. The gateway now says outright whether it settled.
        $payment->update([
            'gateway_reference' => $result->orderReference,
            'status' => $result->settled ? PaymentStatus::Succeeded : PaymentStatus::Pending,
            'paid_at' => $result->settled ? now() : null,
            // Kept so a buyer who comes back to checkout can be returned to the
            // same hosted page instead of being told to wait.
            'metadata' => array_filter([
                ...(array) ($payment->metadata ?? []),
                'redirect_url' => $result->redirectUrl,
            ], fn ($value): bool => $value !== null),
        ]);

        // The buyer is about to leave for the gateway's own page. If they never
        // come back, and the bank's notification is dropped, nothing else would
        // ask what happened until the scheduled sweep runs.
        if ($result->redirectUrl !== null) {
            ReverifyPaymentJob::dispatch($payment->id)->delay(now()->addSeconds(120));
        }

        return $result;
    }

    /**
     * Resolve a payable from its public alias and id.
     *
     * The alias must be listed in `payment-gateway.payables`, and the model must
     * implement Payable. Anything else is refused — without the allowlist a
     * caller could point checkout at any model in the application.
     */
    /**
     * Keyed on what is being bought, not on a hash of the whole request.
     *
     * The old key was `hash(json_encode($data))`, so two buyers paying the same
     * amount for the same thing with identical payloads collided and the second
     * silently received the first's payment — while adding any field at all
     * defeated it.
     */
    private function idempotencyKeyFor(Payable $payable, string $gatewayCode, int $amountCents, string $currency): string
    {
        return hash('sha256', implode(':', [
            $payable->getMorphClass(),
            (string) $payable->getKey(),
            $payable->paymentReference(),
            $gatewayCode,
            // The amount belongs in the key. A gateway rejects a reused
            // idempotency key that arrives with different parameters, so without
            // this a buyer who applied a coupon after a first attempt got a hard
            // failure from Stripe rather than a cheaper checkout. It also means a
            // deliberate change of amount is a new attempt, while a double-submit
            // of the same one still collapses.
            (string) $amountCents,
            $currency,
            // Closed attempts stay in the table and the column is unique, so a
            // retry needs a key of its own.
            //
            // Soft-deleted rows count too. `idempotency_key` is unique across
            // every row, deleted or not, while the guard below only queries live
            // ones — so deleting a payment left its key occupying the index
            // invisibly, and the next attempt at the same amount died on a raw
            // UniqueConstraintViolationException the buyer could do nothing
            // about. Counting deletions here moves the key on instead.
            (string) Payment::withTrashed()
                ->where('payable_type', $payable->getMorphClass())
                ->where('payable_id', $payable->getKey())
                ->where(fn ($query) => $query
                    ->whereIn('status', [PaymentStatus::Failed, PaymentStatus::Canceled])
                    ->orWhereNotNull('deleted_at'))
                ->count(),
        ]));
    }

    /**
     * What to do about an attempt that is already open.
     *
     * Returns a result to hand back, or null to say the old attempt is dead and
     * a fresh one may proceed.
     *
     * The order matters. Resuming the hosted page is best: the buyer carries on
     * where they left off. Failing that we ask the gateway, because "pending" in
     * our table only means nobody has told us otherwise — the buyer may well have
     * paid, and starting a second attempt over the top of that is how somebody
     * gets charged twice.
     *
     * Only when the gateway agrees it is not paid, and enough time has passed
     * that the hosted session is gone, is the attempt written off. Before that
     * fix an abandoned attempt with no stored URL blocked the payable forever:
     * the screen said the payment had started but not completed, and there was
     * no way forward and no way to start again.
     */
    private function resolveOpenAttempt(Payment $existing): ?PaymentResult
    {
        if ($existing->status === PaymentStatus::Succeeded) {
            return PaymentResult::success(
                orderReference: (string) $existing->gateway_reference,
                redirectUrl: null,
                checkoutData: json_encode(['payment_id' => $existing->id, 'idempotent' => true], JSON_THROW_ON_ERROR),
                settled: true,
            );
        }

        $resumeUrl = $existing->metadata['redirect_url'] ?? null;

        if (filled($resumeUrl)) {
            return PaymentResult::success(
                orderReference: (string) $existing->gateway_reference,
                redirectUrl: (string) $resumeUrl,
                checkoutData: json_encode(['payment_id' => $existing->id, 'resumed' => true], JSON_THROW_ON_ERROR),
            );
        }

        if (blank($existing->gateway_reference)) {
            // Never reached the gateway, so there is nothing to double-charge.
            $existing->update(['status' => PaymentStatus::Canceled]);

            return null;
        }

        try {
            VerifyPaymentAction::run([
                'gateway' => $existing->gateway,
                'order_no' => $existing->gateway_reference,
                'transaction_id' => $existing->gateway_reference,
            ]);
        } catch (Throwable) {
            // Could not ask. Not knowing is a reason to wait, not to retry.
            return $this->stillInFlight($existing);
        }

        $fresh = $existing->fresh();

        if ($fresh->status === PaymentStatus::Succeeded) {
            return PaymentResult::success(
                orderReference: (string) $fresh->gateway_reference,
                redirectUrl: null,
                checkoutData: json_encode(['payment_id' => $fresh->id, 'idempotent' => true], JSON_THROW_ON_ERROR),
                settled: true,
            );
        }

        if (in_array($fresh->status, [PaymentStatus::Failed, PaymentStatus::Canceled], true)) {
            return null;
        }

        $abandonedAfter = (int) config('payment-gateway.abandoned_after_minutes', 30);

        if ($fresh->created_at->lte(now()->subMinutes($abandonedAfter))) {
            $fresh->update(['status' => PaymentStatus::Canceled]);

            return null;
        }

        return $this->stillInFlight($fresh);
    }

    private function stillInFlight(Payment $payment): PaymentResult
    {
        return PaymentResult::success(
            orderReference: (string) $payment->gateway_reference,
            redirectUrl: null,
            checkoutData: json_encode(['payment_id' => $payment->id, 'idempotent' => true], JSON_THROW_ON_ERROR),
        );
    }

    /**
     * Drop the fields a public caller must not get to choose.
     *
     * `POST /payment/checkout` is public, and the request body used to be spread
     * straight into the gateway. Two of those fields are decisions, not data:
     *
     *   request_3ds  turning off 3-D Secure moves chargeback liability onto the
     *                merchant. It belongs to whoever configured the gateway.
     *   return_url   becomes Stripe's success_url, so an arbitrary value is an
     *                open redirect off the merchant's own checkout that also
     *                hands the session id to whoever chose it.
     *
     * `return_url` is still honoured when it points back at this application,
     * because a host legitimately uses it to land the buyer on its own page.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withoutCallerControlledSettings(array $data): array
    {
        unset($data['request_3ds']);

        if (isset($data['return_url']) && ! $this->pointsAtThisApplication((string) $data['return_url'])) {
            unset($data['return_url']);
        }

        return $data;
    }

    private function pointsAtThisApplication(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if ($host === null || $host === false) {
            // A relative path never leaves this application.
            return true;
        }

        return strcasecmp($host, (string) parse_url((string) config('app.url'), PHP_URL_HOST)) === 0;
    }

    private function resolvePayable(string $alias, mixed $id): ?Payable
    {
        $class = config('payment-gateway.payables.'.$alias);

        if (! is_string($class) || ! class_exists($class)) {
            return null;
        }

        if (! is_subclass_of($class, Model::class) || ! is_subclass_of($class, Payable::class)) {
            return null;
        }

        $model = $class::query()->find($id);

        return $model instanceof Payable ? $model : null;
    }
}
