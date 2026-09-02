<?php

namespace Kreetancraft\PaymentGateway\Contracts;

use Kreetancraft\PaymentGateway\Data\WebhookResult;

interface WebhookHandler
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload, string $gatewayCode): WebhookResult;

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     */
    public function verifySignature(array $payload, array $headers, string $gatewayCode): bool;
}
