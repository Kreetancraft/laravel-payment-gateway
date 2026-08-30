<?php

namespace Kreetancraft\PaymentGateway\Models;

use Spatie\LaravelSettings\Settings;

class GatewayConfig extends Settings
{
    public string $code = '';
    public string $label = '';
    public string $icon = '';
    public array $currencies = [];
    public bool $supports_subscriptions = false;
    public bool $checkout_redirect = false;
    public array $capabilities = [];
    public array $config_fields = [];
    public string $environment = 'demo';

    public static function group(): string
    {
        return 'payment_gateway';
    }

    public static function current(): ?self
    {
        return rescue(function () {
            $settings = app(self::class);
            $settings->code;

            return $settings;
        }, report: false);
    }

    public function supportsCurrency(string $currency): bool
    {
        $currencies = $this->currencies;
        if (empty($currencies)) {
            return true;
        }

        return in_array(strtoupper($currency), array_map('strtoupper', $currencies), true);
    }
}