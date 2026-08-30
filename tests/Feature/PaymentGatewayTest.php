<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Kreetancraft\PaymentGateway\Actions\ChargePaymentAction;
use Kreetancraft\PaymentGateway\Actions\HandleWebhookAction;
use Kreetancraft\PaymentGateway\Actions\RefundPaymentAction;
use Kreetancraft\PaymentGateway\Actions\VerifyPaymentAction;
use Kreetancraft\PaymentGateway\Contracts\PaymentGateway;
use Kreetancraft\PaymentGateway\Data\PaymentResult;
use Kreetancraft\PaymentGateway\Data\RefundResult;
use Kreetancraft\PaymentGateway\Data\VerificationResult;
use Kreetancraft\PaymentGateway\Data\WebhookResult;
use Kreetancraft\PaymentGateway\Models\Payment;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Config::set('payment-gateway.gateways.stripe.enabled', true);
    Config::set('payment-gateway.gateways.stripe.secret_key', 'sk_test_mock');
    Config::set('payment-gateway.webhook.verify_signature', false);
});

it('charges via stripe mock and creates payment', function (): void {
    $mockGateway = Mockery::mock(PaymentGateway::class);
    $mockGateway->shouldReceive('supportsCurrency')->andReturn(true);
    $mockGateway->shouldReceive('charge')->once()->andReturn(
        PaymentResult::success(orderReference: 'pi_mock_123', redirectUrl: null, checkoutData: json_encode(['client_secret' => 'cs_test_mock']))
    );

    $this->app->instance(PaymentGateway::class, $mockGateway);

    $resolver = Mockery::mock(\Kreetancraft\PaymentGateway\Contracts\GatewayResolver::class);
    $resolver->shouldReceive('getDefaultDriver')->andReturn('stripe');
    $resolver->shouldReceive('resolve')->with('stripe')->andReturn($mockGateway);
    $this->app->instance(\Kreetancraft\PaymentGateway\Contracts\GatewayResolver::class, $resolver);

    $result = ChargePaymentAction::run([
        'amount_cents' => 5000,
        'currency' => 'USD',
        'gateway' => 'stripe',
        'customer_email' => 'test@example.com',
        'description' => 'Test payment',
    ]);

    expect($result->success)->toBeTrue();
    expect($result->orderReference)->toBe('pi_mock_123');

    $payment = Payment::query()->where('gateway_reference', 'pi_mock_123')->first();
    expect($payment)->not->toBeNull();
    expect($payment->amount_cents)->toBe(5000);
    expect($payment->currency)->toBe('USD');
});

it('fails charge with invalid currency for gateway', function (): void {
    $mockGateway = Mockery::mock(PaymentGateway::class);
    $mockGateway->shouldReceive('supportsCurrency')->with('XYZ')->andReturn(false);

    $resolver = Mockery::mock(\Kreetancraft\PaymentGateway\Contracts\GatewayResolver::class);
    $resolver->shouldReceive('getDefaultDriver')->andReturn('stripe');
    $resolver->shouldReceive('resolve')->with('stripe')->andReturn($mockGateway);
    $this->app->instance(\Kreetancraft\PaymentGateway\Contracts\GatewayResolver::class, $resolver);

    $result = ChargePaymentAction::run([
        'amount_cents' => 1000,
        'currency' => 'XYZ',
        'gateway' => 'stripe',
    ]);

    expect($result->success)->toBeFalse();
    expect($result->errorCode)->toBe('currency_not_supported');
});

it('refunds a payment via mock gateway', function (): void {
    $payment = Payment::factory()->create([
        'gateway' => 'stripe',
        'gateway_reference' => 'pi_refund_123',
        'amount_cents' => 10000,
        'currency' => 'USD',
        'status' => 'succeeded',
        'refunded_amount_cents' => 0,
    ]);

    $mockGateway = Mockery::mock(PaymentGateway::class);
    $mockGateway->shouldReceive('refund')->once()->with('pi_refund_123', 50.0)->andReturn(
        RefundResult::success(transactionId: 'pi_refund_123', amount: 50.0, refundId: 're_mock_123')
    );

    $resolver = Mockery::mock(\Kreetancraft\PaymentGateway\Contracts\GatewayResolver::class);
    $resolver->shouldReceive('resolve')->with('stripe')->andReturn($mockGateway);
    $this->app->instance(\Kreetancraft\PaymentGateway\Contracts\GatewayResolver::class, $resolver);

    $result = RefundPaymentAction::run(transactionId: 'pi_refund_123', amount: 50.0);

    expect($result->success)->toBeTrue();
    expect($result->refundId)->toBe('re_mock_123');

    $payment->refresh();
    expect($payment->refunded_amount_cents)->toBe(5000);
    expect($payment->status)->toBe('partially_refunded');
});

it('fails refund when amount exceeds balance', function (): void {
    Payment::factory()->create([
        'gateway' => 'stripe',
        'gateway_reference' => 'pi_over_123',
        'amount_cents' => 1000,
        'currency' => 'USD',
        'status' => 'succeeded',
        'refunded_amount_cents' => 0,
    ]);

    $result = RefundPaymentAction::run(transactionId: 'pi_over_123', amount: 50.0);

    expect($result->success)->toBeFalse();
    expect($result->errorCode)->toBe('amount_exceeds_balance');
});

it('verifies payment via gateway', function (): void {
    $mockGateway = Mockery::mock(PaymentGateway::class);
    $mockGateway->shouldReceive('verify')->once()->andReturn(
        VerificationResult::success(transactionId: 'pi_verify_123', status: 'succeeded', amount: 100.0, currency: 'USD')
    );

    $resolver = Mockery::mock(\Kreetancraft\PaymentGateway\Contracts\GatewayResolver::class);
    $resolver->shouldReceive('getDefaultDriver')->andReturn('stripe');
    $resolver->shouldReceive('resolve')->with('stripe')->andReturn($mockGateway);
    $this->app->instance(\Kreetancraft\PaymentGateway\Contracts\GatewayResolver::class, $resolver);

    $result = VerifyPaymentAction::run(['transaction_id' => 'pi_verify_123', 'gateway' => 'stripe']);

    expect($result->success)->toBeTrue();
    expect($result->status)->toBe('succeeded');
});

it('handles webhook and updates payment status', function (): void {
    $payment = Payment::factory()->create([
        'gateway' => 'stripe',
        'gateway_reference' => 'pi_webhook_123',
        'status' => 'pending',
    ]);

    $mockGateway = Mockery::mock(PaymentGateway::class);
    $mockGateway->shouldReceive('webhook')->once()->andReturn(
        WebhookResult::success(eventType: 'payment_intent.succeeded', transactionId: 'pi_webhook_123', status: 'succeeded', amount: 10.0, currency: 'USD')
    );

    $resolver = Mockery::mock(\Kreetancraft\PaymentGateway\Contracts\GatewayResolver::class);
    $resolver->shouldReceive('resolve')->with('stripe')->andReturn($mockGateway);
    $this->app->instance(\Kreetancraft\PaymentGateway\Contracts\GatewayResolver::class, $resolver);

    $result = HandleWebhookAction::run(gateway: 'stripe', payload: ['id' => 'evt_123', 'type' => 'payment_intent.succeeded'], headers: []);

    expect($result->success)->toBeTrue();

    $payment->refresh();
    expect($payment->status)->toBe('succeeded');
});

it('exposes gateways via api endpoint', function (): void {
    $response = $this->getJson(route('api.payment.gateways'));

    $response->assertOk();
    $response->assertJsonStructure(['enabled', 'gateways']);
});

it('validates charge endpoint returns 422 on missing amount', function (): void {
    $response = $this->postJson(route('api.payment.checkout'), [
        'currency' => 'USD',
        'gateway' => 'stripe',
    ]);

    $response->assertStatus(422);
    $response->assertJson(['success' => false]);
});
