<?php

namespace Kreetancraft\PaymentGateway\Contracts;

interface WebhookHandler
{
    public function handle(array $payload, string $gatewayCode): WebhookResult;

    public function verifySignature(array $payload, array $headers, string $gatewayCode): bool;
}