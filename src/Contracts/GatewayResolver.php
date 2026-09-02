<?php

namespace Kreetancraft\PaymentGateway\Contracts;

use Kreetancraft\PaymentGateway\Models\Gateway;

interface GatewayResolver
{
    public function resolve(string $gatewayCode): ?PaymentGateway;

    public function resolveClass(string $gatewayCode): ?string;

    /**
     * @return list<string>
     */
    public function getEnabledGateways(): array;

    public function getGatewayConfig(string $gatewayCode): ?GatewayConfig;

    public function getGatewayModel(string $gatewayCode): ?Gateway;

    public function getDefaultDriver(): ?string;

    /**
     * @param  array<string, mixed>  $data
     */
    public function getCheckoutRoute(array $data = []): string;
}
