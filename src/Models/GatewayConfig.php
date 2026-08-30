<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Models;

use Kreetancraft\PaymentGateway\Contracts\GatewayConfig as GatewayConfigContract;

class GatewayConfig implements GatewayConfigContract
{
    public function __construct(
        public string $code = '',
        public string $label = '',
        public string $icon = '',
        public array $currencies = [],
        public bool $supports_subscriptions = false,
        public bool $checkout_redirect = false,
        public array $capabilities = [],
        public array $config_fields = [],
        public string $environment = 'demo',
        public array $credentials = [],
    ) {}

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->label;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function getSupportedCurrencies(): array
    {
        return $this->currencies;
    }

    public function supportsCurrency(string $currency): bool
    {
        if (empty($this->currencies)) {
            return true;
        }

        return in_array(strtoupper($currency), array_map('strtoupper', $this->currencies), true);
    }

    public function checkoutRedirect(): bool
    {
        return $this->checkout_redirect;
    }

    public function getCapabilities(): array
    {
        return $this->capabilities;
    }

    public function getConfigFields(): array
    {
        return $this->config_fields;
    }

    public function getConfigValue(string $key, $default = null): mixed
    {
        return $this->credentials[$key] ?? $default;
    }
}
