<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Kreetancraft\PaymentGateway\Contracts\GatewayResolver;
use Kreetancraft\PaymentGateway\Contracts\Payable;
use Kreetancraft\PaymentGateway\Data\PaymentResult;
use Kreetancraft\PaymentGateway\Enums\PaymentStatus;
use Kreetancraft\PaymentGateway\Models\Payment;
use Lorisleiva\Actions\Concerns\AsAction;

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
            'gateway' => ['sometimes', 'string'],
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

        $amountCents = $payable->paymentAmountCents();

        if ($amountCents <= 0) {
            return PaymentResult::failure(
                orderReference: $payable->paymentReference(),
                errorMessage: 'There is nothing left to pay on this.',
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

        $currency = strtoupper($payable->paymentCurrency());

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
        $idempotencyKey = hash('sha256', implode(':', [
            $payable->getMorphClass(),
            (string) $payable->getKey(),
            $payable->paymentReference(),
            $gatewayCode,
        ]));

        $existing = Payment::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing !== null) {
            return PaymentResult::success(
                orderReference: (string) $existing->gateway_reference,
                redirectUrl: null,
                checkoutData: json_encode(['payment_id' => $existing->id, 'idempotent' => true], JSON_THROW_ON_ERROR)
            );
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
            'metadata' => $data['metadata'] ?? null,
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
            ...$data,
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'reference_seed' => $payable->paymentReference(),
            'description' => $paymentData['description'],
        ]);

        if (! $result->success) {
            $payment->update([
                'gateway_reference' => $result->orderReference ?: null,
                'status' => PaymentStatus::Failed,
            ]);

            return $result;
        }

        $payment->update([
            'gateway_reference' => $result->orderReference,
            'status' => $result->redirectUrl !== null ? PaymentStatus::Pending : PaymentStatus::Succeeded,
            'paid_at' => $result->redirectUrl === null ? now() : null,
        ]);

        return $result;
    }

    /**
     * Resolve a payable from its public alias and id.
     *
     * The alias must be listed in `payment-gateway.payables`, and the model must
     * implement Payable. Anything else is refused — without the allowlist a
     * caller could point checkout at any model in the application.
     */
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
