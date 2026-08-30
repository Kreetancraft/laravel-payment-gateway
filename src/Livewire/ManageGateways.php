<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Livewire;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Kreetancraft\PaymentGateway\Layout;
use Kreetancraft\PaymentGateway\Models\Gateway;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

class ManageGateways extends Component
{
    #[Url(except: '')]
    public string $search = '';

    public function toggleGatewayEnabled(string $code): void
    {
        $gateway = Gateway::where('code', $code)->firstOrFail();
        $this->authorize('toggle', $gateway);

        $gateway->enabled = ! $gateway->enabled;
        $gateway->save();

        $this->clearGatewayCache();

        if (class_exists(Flux::class) && app()->bound('flux')) {
            Flux::toast(
                variant: 'success',
                text: __('Gateway [:gateway] is now :status.', [
                    'gateway' => $gateway->label,
                    'status' => $gateway->enabled ? 'enabled' : 'disabled',
                ])
            );
        }
    }

    #[Title('Payment Gateways - Admin')]
    public function render(): View
    {
        $this->authorize('viewAny', Gateway::class);

        $gateways = Gateway::query()
            ->when($this->search !== '', fn ($query) => $query->where(fn ($q) => $q->where('label', 'like', "%{$this->search}%")->orWhere('code', 'like', "%{$this->search}%")))
            ->orderBy('label')
            ->get();

        $activeCount = Gateway::where('enabled', true)->count();

        return view('payment-gateway::livewire.manage-gateways', [
            'gateways' => $gateways,
            'activeCount' => $activeCount,
        ])->layout(Layout::admin());
    }

    private function clearGatewayCache(): void
    {
        cache()->forget('payment_gateway.enabled');
        cache()->forget('payment_gateway.all');
    }
}
