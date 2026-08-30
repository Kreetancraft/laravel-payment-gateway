<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Livewire;

use Illuminate\Contracts\View\View;
use Kreetancraft\PaymentGateway\Layout;
use Kreetancraft\PaymentGateway\Models\Gateway;
use Livewire\Attributes\Title;
use Livewire\Component;

class EditGateway extends Component
{
    public string $code;

    public string $label = '';

    public string $icon = '';

    public string $currenciesInput = '';

    public string $environment = 'demo';

    public bool $enabled = true;

    public bool $checkout_redirect = false;

    public array $configFields = [];

    public array $fieldValues = [];

    public function mount(string $code): void
    {
        $gateway = Gateway::where('code', $code)->firstOrFail();
        $this->authorize('update', $gateway);

        $this->code = $gateway->code;
        $this->label = $gateway->label;
        $this->icon = $gateway->icon ?? '';
        $this->currenciesInput = implode(', ', $gateway->currencies ?? []);
        $this->environment = $gateway->environment ?? 'demo';
        $this->enabled = (bool) $gateway->enabled;
        $this->checkout_redirect = (bool) $gateway->checkout_redirect;
        $this->configFields = $gateway->config_fields ?? config("payment-gateway.gateways.{$code}.config_fields", []);

        $this->fieldValues = [];
        foreach ($this->configFields as $key => $field) {
            $fieldKey = is_array($field) ? ($field['key'] ?? (string) $key) : (string) $key;
            $this->fieldValues[$fieldKey] = (string) ($gateway->getCredential($fieldKey) ?? ($field['default'] ?? ''));
        }
    }

    public function save(): void
    {
        $gateway = Gateway::where('code', $this->code)->firstOrFail();
        $this->authorize('update', $gateway);

        $this->validate([
            'label' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:2048'],
            'currenciesInput' => ['nullable', 'string'],
            'environment' => ['required', 'in:demo,test,sandbox,live,production'],
            'enabled' => ['boolean'],
            'checkout_redirect' => ['boolean'],
        ]);

        $currencies = collect(explode(',', $this->currenciesInput))
            ->map(fn (string $c): string => strtoupper(trim($c)))
            ->filter(fn (string $c): bool => $c !== '')
            ->values()
            ->all();

        $gateway->label = $this->label;
        $gateway->icon = $this->icon;
        $gateway->currencies = $currencies;
        $gateway->environment = $this->environment;
        $gateway->enabled = $this->enabled;
        $gateway->checkout_redirect = $this->checkout_redirect;

        $credentials = $gateway->credentials ?? [];
        foreach ($this->fieldValues as $key => $value) {
            $credentials[$key] = $value;
        }
        $gateway->credentials = $credentials;

        $gateway->save();
        $this->clearGatewayCache();

        session()->flash('gateway_message', "Gateway [{$gateway->label}] settings updated successfully.");

        $this->redirect(route(config('payment-gateway.routes.names.gateways', 'admin.payment.gateways')), navigate: true);
    }

    #[Title('Configure Gateway - Admin')]
    public function render(): View
    {
        return view('payment-gateway::livewire.edit-gateway')->layout(Layout::admin());
    }

    private function clearGatewayCache(): void
    {
        cache()->forget('payment_gateway.enabled');
        cache()->forget('payment_gateway.all');
    }
}
