<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Gateways;

use Kreetancraft\PaymentGateway\Contracts\PaymentGateway;
use Kreetancraft\PaymentGateway\Data\PaymentResult;
use Kreetancraft\PaymentGateway\Data\RefundResult;
use Kreetancraft\PaymentGateway\Data\VerificationResult;
use Kreetancraft\PaymentGateway\Data\WebhookResult;
use Kreetancraft\PaymentGateway\Models\Gateway;

abstract class AbstractGateway implements PaymentGateway
{
    protected Gateway $gateway;

    public function __construct(Gateway $gateway)
    {
        $this->gateway = $gateway;
    }

    abstract public function charge(array $data): PaymentResult;

    abstract public function refund(string $transactionId, float $amount): RefundResult;

    abstract public function verify(array $data): VerificationResult;

    abstract public function webhook(array $payload): WebhookResult;

    public function supportsCurrency(string $currency): bool
    {
        return $this->gateway->supportsCurrency($currency);
    }

    public function checkoutRedirect(): bool
    {
        return $this->gateway->usesCheckoutRedirect();
    }

    public function getSupportedCurrencies(): array
    {
        return $this->gateway->getSupportedCurrencies();
    }

    public function getCode(): string
    {
        return $this->gateway->getCode();
    }

    public function getLabel(): string
    {
        return $this->gateway->getLabel();
    }

    public function getIcon(): string
    {
        return $this->gateway->getIcon();
    }

    public function getCapabilities(): array
    {
        return $this->gateway->getCapabilities();
    }

    public function getConfigFields(): array
    {
        return $this->gateway->getConfigFields();
    }

    public function getConfigValue(string $key, $default = null): mixed
    {
        return $this->gateway->getConfigValue($key, $default);
    }

    protected function getConfigValueFromModel(string $key, $default = null): mixed
    {
        return $this->gateway->getConfigValue($key, $default);
    }
}
