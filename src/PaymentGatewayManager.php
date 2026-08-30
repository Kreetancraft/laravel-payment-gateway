<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Manager;
use InvalidArgumentException;
use Kreetancraft\PaymentGateway\Contracts\GatewayConfig;
use Kreetancraft\PaymentGateway\Contracts\GatewayResolver;
use Kreetancraft\PaymentGateway\Contracts\PaymentGateway;
use Kreetancraft\PaymentGateway\Models\Gateway;

/**
 * Resolves gateway drivers from the database.
 *
 * Gateways are stored in the database (payment_gateways table) with encrypted
 * credentials. This allows configuring, adding, and toggling gateways via admin UI
 * without editing config files.
 */
class PaymentGatewayManager extends Manager implements GatewayResolver
{
    public function getDefaultDriver(): ?string
    {
        $configured = config('payment-gateway.default');

        if (filled($configured)) {
            return (string) $configured;
        }

        $enabled = $this->getEnabledGateways();

        if (! empty($enabled)) {
            return $enabled[0];
        }

        return 'stripe';
    }

    /**
     * @return list<string>
     */
    public function getEnabledGateways(): array
    {
        return Cache::remember('payment_gateway.enabled', 300, function (): array {
            return Gateway::query()
                ->enabled()
                ->orderBy('label')
                ->pluck('code')
                ->all();
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getAllGateways(): array
    {
        return Cache::remember('payment_gateway.all', 300, function (): array {
            return Gateway::query()
                ->orderBy('label')
                ->get(['code', 'label', 'icon', 'enabled'])
                ->toArray();
        });
    }

    public function isGatewayEnabled(string $code): bool
    {
        return Gateway::where('code', $code)
            ->where('enabled', true)
            ->exists();
    }

    public function getGatewayConfigModel(string $gatewayCode): ?Gateway
    {
        return Gateway::where('code', $gatewayCode)->first();
    }

    public function getGatewayModel(string $gatewayCode): ?Gateway
    {
        return $this->getGatewayConfigModel($gatewayCode);
    }

    public function getCheckoutRoute(array $data = []): string
    {
        $enabled = $this->getEnabledGateways();

        if (count($enabled) === 1) {
            $gateway = $enabled[0];
            $routeName = (string) config('payment-gateway.routes.names.checkout', 'payment.checkout');

            return route($routeName, array_merge($data, ['gateway' => $gateway]));
        }

        $chooseRoute = 'payment.choose';

        if (Route::has($chooseRoute)) {
            return route($chooseRoute, $data);
        }

        $fallback = (string) config('payment-gateway.routes.names.checkout', 'payment.checkout');

        return route($fallback, $data);
    }

    /**
     * Create a driver instance for the given gateway code.
     */
    public function createDriver($driver): PaymentGateway
    {
        $gateway = Gateway::where('code', $driver)->first();

        if (! $gateway) {
            throw new InvalidArgumentException("Payment gateway driver [{$driver}] not found in database.");
        }

        if (! $gateway->isEnabled()) {
            throw new InvalidArgumentException("Payment gateway [{$driver}] is disabled.");
        }

        if (! $gateway->isConfigured()) {
            throw new InvalidArgumentException("Payment gateway [{$driver}] is not properly configured. Missing required credentials.");
        }

        $class = (string) $gateway->getClassName();

        if (! class_exists($class)) {
            throw new InvalidArgumentException("Payment gateway class [{$class}] for driver [{$driver}] does not exist.");
        }

        // Use container to resolve gateway instance with constructor dependency injection (e.g. HblClient)
        return $this->container->make($class, [
            'gateway' => $gateway,
        ]);
    }

    public function resolve(string $gatewayCode): ?PaymentGateway
    {
        $gateway = Gateway::where('code', $gatewayCode)->first();

        if (! $gateway || ! $gateway->isEnabled()) {
            return null;
        }

        try {
            return $this->driver($gatewayCode);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public function resolveClass(string $gatewayCode): ?string
    {
        $gateway = Gateway::where('code', $gatewayCode)->first();

        if (! $gateway) {
            return null;
        }

        return $gateway->class;
    }

    public function getGatewayConfig(string $gatewayCode): ?GatewayConfig
    {
        $gateway = Gateway::where('code', $gatewayCode)->first();

        if (! $gateway) {
            return null;
        }

        return $this->makeGatewayConfig($gateway);
    }

    private function makeGatewayConfig(Gateway $gateway): GatewayConfig
    {
        return new class($gateway) implements GatewayConfig
        {
            public function __construct(
                private readonly Gateway $gateway,
            ) {}

            public function getCode(): string
            {
                return $this->gateway->code;
            }

            public function getName(): string
            {
                return $this->gateway->getLabel();
            }

            public function getLabel(): string
            {
                return $this->gateway->getDisplayLabel();
            }

            public function getIcon(): string
            {
                return $this->gateway->getIcon();
            }

            public function getSupportedCurrencies(): array
            {
                return $this->gateway->getSupportedCurrencies();
            }

            public function supportsCurrency(string $currency): bool
            {
                return $this->gateway->supportsCurrency($currency);
            }

            public function checkoutRedirect(): bool
            {
                return $this->gateway->usesCheckoutRedirect();
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
                return $this->gateway->getCredential($key, $default);
            }
        };
    }
}
