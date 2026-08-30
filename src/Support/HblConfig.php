<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Support;

/**
 * Resolves Himalayan Bank settings from config('payment-gateway.gateways.himalayan').
 */
class HblConfig
{
    public static function env(): string
    {
        return (string) config('payment-gateway.gateways.himalayan.environment', config('payment-gateway.gateways.himalayan.env', 'demo'));
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $direct = config("payment-gateway.gateways.himalayan.{$key}");

        if ($direct !== null) {
            return $direct;
        }

        return config("payment-gateway.gateways.himalayan." . self::env() . ".{$key}", $default);
    }

    /**
     * Resolve a configured key-file path. Project-relative values from .env
     * (e.g. `storage/app/hbl/merchant_signing.key`) are anchored to base_path()
     * so they work regardless of the process working directory.
     */
    public static function keyPath(string $key): ?string
    {
        $path = self::get($key);

        if (blank($path)) {
            return null;
        }

        $path = (string) $path;

        if (preg_match('#^([A-Za-z]:[\\\\/]|[\\\\/])#', $path) === 1) {
            return $path;
        }

        return base_path($path);
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
