<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Actions;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Kreetancraft\PaymentGateway\Contracts\GatewayResolver;
use Kreetancraft\PaymentGateway\Data\PaymentResult;
use Kreetancraft\PaymentGateway\Models\Payment;
use Lorisleiva\Actions\Concerns\AsAction;

class ChargePaymentAction
{
    use AsAction;

    public function __construct(
        protected GatewayResolver $resolver,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function handle(array $data): PaymentResult
    {
        $validator = Validator::make($data, [
            'amount_cents' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
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

        $gatewayCode = (string) ($data['gateway'] ?? $validated['gateway'] ?? $this->resolver->getDefaultDriver() ?? 'stripe');

        if (blank($gatewayCode)) {
            return PaymentResult::failure(
                orderReference: '',
                errorMessage: "No gateway specified and no default gateway configured.",
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

        $currency = strtoupper((string) $validated['currency']);

        if (! $gateway->supportsCurrency($currency)) {
            return PaymentResult::failure(
                orderReference: '',
                errorMessage: "Currency [{$currency}] is not supported by gateway [{$gatewayCode}].",
                errorCode: 'currency_not_supported'
            );
        }

        $idempotencyKey = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));

        $existing = Payment::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing !== null) {
            return PaymentResult::success(
                orderReference: (string) $existing->gateway_reference,
                redirectUrl: null,
                checkoutData: json_encode(['payment_id' => $existing->id, 'idempotent' => true], JSON_THROW_ON_ERROR)
            );
        }

        $result = $gateway->charge($data);

        if (! $result->success) {
            Payment::create([
                'amount_cents' => (int) $validated['amount_cents'],
                'currency' => $currency,
                'gateway' => $gatewayCode,
                'gateway_reference' => $result->orderReference ?: null,
                'status' => 'failed',
                'idempotency_key' => $idempotencyKey,
                'customer_email' => $data['customer_email'] ?? null,
                'customer_name' => $data['customer_name'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'customer_address' => $data['customer_address'] ?? null,
                'description' => $data['description'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ]);

            return $result;
        }

        Payment::create([
            'amount_cents' => (int) $validated['amount_cents'],
            'currency' => $currency,
            'gateway' => $gatewayCode,
            'gateway_reference' => $result->orderReference,
            'status' => $result->redirectUrl !== null ? 'pending' : 'succeeded',
            'idempotency_key' => $idempotencyKey,
            'customer_email' => $data['customer_email'] ?? null,
            'customer_name' => $data['customer_name'] ?? null,
            'customer_phone' => $data['customer_phone'] ?? null,
            'customer_address' => $data['customer_address'] ?? null,
            'description' => $data['description'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'paid_at' => $result->redirectUrl === null ? now() : null,
        ]);

        return $result;
    }
}
