<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Kreetancraft\PaymentGateway\Models\Gateway;

class ManageGateways extends Component
{
    public bool $showEditModal = false;

    public ?string $editingCode = null;

    public string $editingLabel = '';

    public string $editingIcon = '';

    public string $editingCurrencies = '';

    public bool $editingEnabled = true;

    public array $configFields = [];

    public array $fieldValues = [];

    public function toggleGatewayEnabled(string $code): void
    {
        $gateway = Gateway::where('code', $code)->firstOrFail();
        $gateway->enabled = ! $gateway->enabled;
        $gateway->save();

        $this->clearGatewayCache();

        session()->flash('gateway_message', "Gateway [{$code}] " . ($gateway->enabled ? 'enabled' : 'disabled') . '.');
    }

    public function openEditGatewayModal(string $code): void
    {
        $gateway = Gateway::where('code', $code)->firstOrFail();

        $this->editingCode = $code;
        $this->editingLabel = $gateway->label;
        $this->editingIcon = $gateway->icon ?? '';
        $this->editingCurrencies = implode(', ', $gateway->currencies ?? []);
        $this->editingEnabled = $gateway->enabled;
        $this->configFields = $gateway->config_fields ?? $this->getDefaultConfigFields($code);
        $this->fieldValues = [];

        foreach ($this->configFields as $key => $field) {
            $this->fieldValues[$key] = (string) ($gateway->getCredential($key) ?? $field['default'] ?? '');
        }

        $this->showEditModal = true;
    }

    public function saveGatewayCredentials(): void
    {
        if (blank($this->editingCode)) {
            return;
        }

        $this->validateGatewayForm();

        $gateway = Gateway::where('code', $this->editingCode)->firstOrFail();

        $currencies = $this->parseCurrencies($this->editingCurrencies);

        $gateway->label = $this->editingLabel;
        $gateway->icon = $this->editingIcon;
        $gateway->currencies = $currencies;
        $gateway->enabled = $this->editingEnabled;

        foreach ($this->fieldValues as $key => $value) {
            $gateway->setCredential($key, $value);
        }

        $gateway->save();
        $this->clearGatewayCache();

        $this->showEditModal = false;
        $this->editingCode = null;

        session()->flash('gateway_message', "Gateway [{$gateway->code}] saved. Keys encrypted in database.");
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->editingCode = null;
    }

    public function render(): View
    {
        return view('payment-gateway::livewire.manage-gateways', [
            'gateways' => $this->loadGatewaysFromDatabase(),
        ]);
    }

    private function loadGatewaysFromDatabase(): array
    {
        return Gateway::orderBy('label')->get()->map(fn (Gateway $g) => [
            'code' => $g->code,
            'label' => $g->label,
            'icon' => $g->icon,
            'currencies' => $g->currencies ?? [],
            'capabilities' => $g->capabilities ?? [],
            'checkout_redirect' => $g->checkout_redirect,
            'enabled' => $g->enabled,
            'fields' => $g->config_fields ?? $this->getDefaultConfigFields($g->code),
        ])->all();
    }

    private function validateGatewayForm(): void
    {
        $this->validate([
            'editingLabel' => ['required', 'string', 'max:255'],
            'editingIcon' => ['nullable', 'string', 'max:2048'],
            'editingCurrencies' => ['nullable', 'string'],
        ]);
    }

    private function parseCurrencies(string $input): array
    {
        return collect(explode(',', $input))
            ->map(fn (string $c): string => strtoupper(trim($c)))
            ->filter(fn (string $c): bool => $c !== '')
            ->values()
            ->all();
    }

    private function clearGatewayCache(): void
    {
        cache()->forget('payment_gateway.enabled');
        cache()->forget('payment_gateway.all');
    }

    private function getDefaultConfigFields(string $code): array
    {
        return config("payment-gateway.gateways.{$code}.config_fields", []);
    }
}
