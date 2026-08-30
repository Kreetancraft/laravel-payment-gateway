<?php

namespace Kreetancraft\PaymentGateway\Contracts;

use Kreetancraft\PaymentGateway\Data\PaymentResult;
use Kreetancraft\PaymentGateway\Data\RefundResult;
use Kreetancraft\PaymentGateway\Data\VerificationResult;
use Kreetancraft\PaymentGateway\Data\WebhookResult;

interface PaymentGateway
{
    public function charge(array $data): PaymentResult;

    public function refund(string $transactionId, float $amount): RefundResult;

    public function verify(array $data): VerificationResult;

    public function webhook(array $payload): WebhookResult;

    public function supportsCurrency(string $currency): bool;

    public function checkoutRedirect(): bool;

    public function getSupportedCurrencies(): array;

    public function getCode(): string;

    public function getLabel(): string;

    public function getIcon(): string;
}