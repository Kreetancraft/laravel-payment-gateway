<?php

namespace Kreetancraft\PaymentGateway\Contracts;

interface GatewayConfig
{
    public function getCode(): string;

    public function getName(): string;

    public function getLabel(): string;

    public function getIcon(): string;

    public function getSupportedCurrencies(): array;

    public function supportsCurrency(string $currency): bool;

    public function checkoutRedirect(): bool;

    public function getCapabilities(): array;

    public function getConfigFields(): array;

    public function getConfigValue(string $key, $default = null): mixed;
}