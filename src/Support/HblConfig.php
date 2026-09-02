<?php

namespace Kreetancraft\PaymentGateway\Support;

/**
 * Resolves runtime Himalayan Bank settings from config('payment-gateway.gateways.himalayan').
 */
class HblConfig
{
    public static function env(): string
    {
        return (string) config('payment-gateway.gateways.himalayan.environment', config('payment-gateway.gateways.himalayan.env', 'demo'));
    }

    public static function baseUrl(): string
    {
        $direct = config('payment-gateway.gateways.himalayan.base_url');
        if (filled($direct)) {
            return (string) $direct;
        }

        return match (strtolower(self::env())) {
            'production', 'live' => 'https://core.paco.2c2p.com/',
            default => 'https://core.demo-paco.2c2p.com/',
        };
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $direct = config("payment-gateway.gateways.himalayan.{$key}");

        if ($direct !== null) {
            return $direct;
        }

        return config('payment-gateway.gateways.himalayan.'.self::env().".{$key}", $default);
    }

    /**
     * @return list<string>
     */
    public static function paidStatuses(): array
    {
        return (array) config('payment-gateway.gateways.himalayan.paid_statuses', config('payment-gateway.gateways.himalayan.paidStatuses', []));
    }

    /**
     * @return list<string>
     */
    public static function failedStatuses(): array
    {
        return (array) config('payment-gateway.gateways.himalayan.failed_statuses', config('payment-gateway.gateways.himalayan.failedStatuses', []));
    }
}
