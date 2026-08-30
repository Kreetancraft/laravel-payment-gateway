<div class="space-y-6">
    <x-payment-gateway::page-header
        :title="__('Payment Gateways')"
        :subtitle="__('Enable, configure, and manage payment providers for customer checkouts.')"
    >
        <x-slot:breadcrumbs>
            <flux:breadcrumbs>
                <flux:breadcrumbs.item href="{{ \Kreetancraft\PaymentGateway\Layout::home() }}" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>{{ __('Gateways') }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>
        </x-slot:breadcrumbs>

        <x-slot:badges>
            <flux:badge size="sm" color="zinc" icon="credit-card">
                {{ __(':count total', ['count' => $gateways->count()]) }}
            </flux:badge>
            <flux:badge size="sm" color="green" icon="check-circle">
                {{ __(':count active', ['count' => $activeCount]) }}
            </flux:badge>
        </x-slot:badges>
    </x-payment-gateway::page-header>

    <flux:separator variant="subtle" />

    @if (session()->has('gateway_message'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('gateway_message') }}
        </flux:callout>
    @endif

    {{-- Filters --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="relative w-full sm:max-w-xs">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('Search gateways…') }}"
                icon="magnifying-glass"
                size="sm"
            />
            <div wire:loading wire:target="search" class="absolute inset-y-0 right-3 flex items-center">
                <flux:icon icon="arrow-path" variant="mini" class="animate-spin opacity-50" />
            </div>
        </div>
    </div>

    @if ($gateways->isEmpty())
        <x-payment-gateway::empty-state
            icon="credit-card"
            :title="__('No payment gateways found')"
            :description="__('Run the gateway seeder or sync configuration to register payment providers.')"
        />
    @else
        <flux:card class="overflow-hidden p-0">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Gateway') }}</flux:table.column>
                    <flux:table.column>{{ __('Currencies') }}</flux:table.column>
                    <flux:table.column>{{ __('Environment') }}</flux:table.column>
                    <flux:table.column>{{ __('Redirect') }}</flux:table.column>
                    <flux:table.column>{{ __('Enabled') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($gateways as $gw)
                        <flux:table.row wire:key="gw-{{ $gw->code }}">
                            <flux:table.cell>
                                <div class="flex items-center gap-3">
                                    @if (filled($gw->icon))
                                        <img src="{{ $gw->icon }}" alt="{{ $gw->label }}" class="h-6 w-auto object-contain" />
                                    @endif
                                    <div>
                                        <div class="font-medium text-sm">{{ $gw->label }}</div>
                                        <div class="text-xs text-zinc-500 font-mono">{{ $gw->code }}</div>
                                    </div>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($gw->currencies ?? [] as $cur)
                                        <flux:badge size="sm" color="zinc">{{ $cur }}</flux:badge>
                                    @endforeach
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$gw->environment === 'live' || $gw->environment === 'production' ? 'emerald' : 'amber'">
                                    {{ ucfirst($gw->environment ?? 'demo') }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$gw->checkout_redirect ? 'sky' : 'zinc'">
                                    {{ $gw->checkout_redirect ? __('Hosted Redirect') : __('Inline / Elements') }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:switch
                                    wire:click="toggleGatewayEnabled('{{ $gw->code }}')"
                                    :checked="$gw->enabled"
                                />
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="cog-6-tooth"
                                    href="{{ route(config('payment-gateway.routes.names.gateways_edit', 'admin.payment.gateways.edit'), $gw->code) }}"
                                    wire:navigate
                                >
                                    {{ __('Configure') }}
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif
</div>
