<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Gateways;

use Kreetancraft\PaymentGateway\Data\PaymentResult;
use Kreetancraft\PaymentGateway\Data\RefundResult;
use Kreetancraft\PaymentGateway\Data\VerificationResult;
use Kreetancraft\PaymentGateway\Data\WebhookResult;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Stripe;
use Stripe\Webhook;

class StripeGateway extends AbstractGateway
{
    public function charge(array $data): PaymentResult
    {
        try {
            Stripe::setApiKey($this->gateway->getStripeSecretKey());

            $paymentIntent = PaymentIntent::create([
                'amount' => $data['amount_cents'],
                'currency' => strtolower($data['currency']),
                'payment_method_types' => ['card'],
                'description' => $data['description'] ?? '',
                'metadata' => array_merge(
                    $data['metadata'] ?? [],
                    [
                        'customer_email' => $data['customer_email'] ?? '',
                        'customer_name' => $data['customer_name'] ?? '',
                        'customer_phone' => $data['customer_phone'] ?? '',
                        'customer_address' => $data['customer_address'] ?? '',
                    ]
                ),
                'receipt_email' => $data['customer_email'] ?? null,
                'setup_future_usage' => $data['setup_future_usage'] ?? null,
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ]);

            return PaymentResult::success(
                orderReference: $paymentIntent->id,
                redirectUrl: null,
                checkoutData: json_encode([
                    'client_secret' => $paymentIntent->client_secret,
                    'payment_intent_id' => $paymentIntent->id,
                ])
            );
        } catch (ApiErrorException $e) {
            return PaymentResult::failure(
                orderReference: $data['order_reference'] ?? '',
                errorMessage: $e->getMessage(),
                errorCode: $e->getCode()
            );
        }
    }

    public function refund(string $transactionId, float $amount): RefundResult
    {
        try {
            Stripe::setApiKey($this->gateway->getStripeSecretKey());

            $refund = Refund::create([
                'payment_intent' => $transactionId,
                'amount' => (int) round($amount * 100),
            ]);

            return RefundResult::success(
                transactionId: $transactionId,
                amount: $amount,
                refundId: $refund->id
            );
        } catch (ApiErrorException $e) {
            return RefundResult::failure(
                transactionId: $transactionId,
                amount: $amount,
                errorMessage: $e->getMessage(),
                errorCode: $e->getCode()
            );
        }
    }

    public function verify(array $data): VerificationResult
    {
        try {
            Stripe::setApiKey($this->gateway->getStripeSecretKey());

            $paymentIntent = PaymentIntent::retrieve($data['payment_intent_id'] ?? $data['transaction_id'] ?? '');

            return VerificationResult::success(
                transactionId: $paymentIntent->id,
                status: $paymentIntent->status,
                amount: $paymentIntent->amount / 100,
                currency: strtoupper($paymentIntent->currency),
                paidAt: $paymentIntent->status === 'succeeded' ? now()->toDateTimeString() : null
            );
        } catch (ApiErrorException $e) {
            return VerificationResult::failure(
                transactionId: $data['transaction_id'] ?? '',
                errorMessage: $e->getMessage()
            );
        }
    }

    public function webhook(array $payload): WebhookResult
    {
        $webhookSecret = $this->gateway->getStripeWebhookSecret();

        try {
            $event = Webhook::constructFrom(
                json_encode($payload),
                $this->gateway->getStripeWebhookSecret()
            );

            $eventType = $event->type;
            $paymentIntent = $event->data->object ?? null;

            if (! $paymentIntent) {
                return WebhookResult::failure($eventType, '', 'No payment intent in webhook');
            }

            $statusMap = [
                'payment_intent.succeeded' => 'succeeded',
                'payment_intent.payment_failed' => 'failed',
                'payment_intent.canceled' => 'canceled',
                'payment_intent.requires_action' => 'requires_action',
            ];

            $status = $statusMap[$eventType] ?? 'pending';

            return WebhookResult::success(
                eventType: $eventType,
                transactionId: $paymentIntent->id ?? '',
                status: $status,
                amount: ($paymentIntent->amount ?? 0) / 100,
                currency: strtoupper($paymentIntent->currency ?? ''),
            );
        } catch (\Exception $e) {
            return WebhookResult::failure(
                eventType: 'unknown',
                transactionId: '',
                errorMessage: $e->getMessage()
            );
        }
    }

    public function supportsCurrency(string $currency): bool
    {
        return in_array(strtoupper($currency), array_map('strtoupper', $this->getSupportedCurrencies()));
    }

    public function checkoutRedirect(): bool
    {
        return false;
    }

    public function getSupportedCurrencies(): array
    {
        return $this->gateway->getSupportedCurrencies();
    }

    public function getCode(): string
    {
        return 'stripe';
    }

    public function getLabel(): string
    {
        return 'Pay with Stripe';
    }

    public function getIcon(): string
    {
        return 'https://js.stripe.com/v3/stripe-logo.svg';
    }
}
