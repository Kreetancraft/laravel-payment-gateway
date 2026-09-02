<?php

namespace Kreetancraft\PaymentGateway\Gateways;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Kreetancraft\PaymentGateway\Contracts\PaymentGateway;
use Kreetancraft\PaymentGateway\Data\PaymentResult;
use Kreetancraft\PaymentGateway\Data\RefundResult;
use Kreetancraft\PaymentGateway\Data\VerificationResult;
use Kreetancraft\PaymentGateway\Data\WebhookResult;
use Kreetancraft\PaymentGateway\Models\Gateway;

abstract class AbstractGateway implements PaymentGateway
{
    public function __construct(protected Gateway $gateway) {}

    abstract public function charge(array $data): PaymentResult;

    abstract public function refund(string $transactionId, float $amount): RefundResult;

    abstract public function verify(array $data): VerificationResult;

    abstract public function webhook(Request $request): WebhookResult;

    public function supportsCurrency(string $currency): bool
    {
        return $this->gateway->supportsCurrency($currency);
    }

    public function checkoutRedirect(): bool
    {
        return $this->gateway->usesCheckoutRedirect();
    }

    public function getSupportedCurrencies(): array
    {
        return $this->gateway->getSupportedCurrencies();
    }

    public function getCode(): string
    {
        return $this->gateway->getCode();
    }

    public function getLabel(): string
    {
        return $this->gateway->getLabel();
    }

    public function getIcon(): string
    {
        return $this->gateway->getIcon();
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
        return $this->gateway->getConfigValue($key, $default);
    }

    protected function getConfigValueFromModel(string $key, $default = null): mixed
    {
        return $this->gateway->getConfigValue($key, $default);
    }

    /**
     * Where the gateway should send the buyer back to.
     *
     * Shared by every gateway so a host application configures the return
     * points once. An explicit URL in config wins, then a named route, and only
     * then the package's own default path.
     *
     * @param  array<string, mixed>  $params
     */
    protected function resolveRedirectUrl(string $type, array $params = [], ?string $overrideUrl = null): string
    {
        $query = http_build_query($params);

        if (filled($overrideUrl)) {
            $separator = str_contains($overrideUrl, '?') ? '&' : '?';

            return "{$overrideUrl}{$separator}{$query}";
        }

        $configUrl = config("payment-gateway.routes.redirect_urls.{$type}");
        if (filled($configUrl)) {
            if (filter_var($configUrl, FILTER_VALIDATE_URL)) {
                $separator = str_contains((string) $configUrl, '?') ? '&' : '?';

                return "{$configUrl}{$separator}{$query}";
            }
            if (Route::has((string) $configUrl)) {
                return route((string) $configUrl, $params);
            }
        }

        $routeName = config("payment-gateway.routes.names.{$type}", "payment.{$type}");
        if (Route::has($routeName)) {
            return route($routeName, $params);
        }

        return url("/payment/{$type}?{$query}");
    }
}
