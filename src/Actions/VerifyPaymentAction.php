<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Actions;

use Kreetancraft\PaymentGateway\Contracts\GatewayResolver;
use Kreetancraft\PaymentGateway\Data\VerificationResult;
use Kreetancraft\PaymentGateway\Models\Payment;
use Lorisleiva\Actions\Concerns\AsAction;

class VerifyPaymentAction
{
    use AsAction;

    public function __construct(
        protected GatewayResolver $resolver,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): VerificationResult
    {
        $gatewayCode = (string) ($data['gateway'] ?? '');

        if (blank($gatewayCode)) {
            $transactionId = (string) ($data['transaction_id'] ?? $data['order_no'] ?? $data['payment_intent_id'] ?? '');

            if (filled($transactionId)) {
                $payment = Payment::query()->where('gateway_reference', $transactionId)->first();

                if ($payment !== null) {
                    $gatewayCode = $payment->gateway;
                }
            }
        }

        if (blank($gatewayCode)) {
            $gatewayCode = (string) ($this->resolver->getDefaultDriver() ?? 'stripe');
        }

        $gateway = $this->resolver->resolve($gatewayCode);

        if ($gateway === null) {
            return VerificationResult::failure(
                transactionId: (string) ($data['transaction_id'] ?? $data['order_no'] ?? ''),
                errorMessage: "Gateway [{$gatewayCode}] is not configured or not enabled."
            );
        }

        return $gateway->verify($data);
    }
}
