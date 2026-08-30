<div class="space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ \Kreetancraft\PaymentGateway\Layout::home() }}" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Gateways') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="space-y-2">
            <flux:heading size="xl" level="1">{{ __('Payment Gateways') }}</flux:heading>
            <flux:subheading class="max-w-xl">{{ __('Enable, configure, and manage payment providers for customer checkouts.') }}</flux:subheading>

            <div class="flex flex-wrap items-center gap-2 pt-1">
                <flux:badge size="sm" color="zinc" icon="credit-card">
                    {{ __(':count total', ['count' => $gateways->count()]) }}
                </flux:badge>
                <flux:badge size="sm" color="emerald" icon="check-circle">
                    {{ __(':count active', ['count' => $activeCount]) }}
                </flux:badge>
            </div>
        </div>
    </div>

    <flux:separator variant="subtle" />

    {{-- Filters --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="relative w-full sm:max-w-xs">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('Search gateways…') }}"
                icon="magnifying-glass"
            />
            <div
                wire:loading.flex
                wire:target="search"
                class="absolute inset-y-0 right-3 items-center"
            >
                <flux:icon icon="arrow-path" variant="mini" class="animate-spin opacity-50" />
            </div>
        </div>

        @if ($search)
            <flux:button variant="subtle" size="sm" icon="x-mark" wire:click="$set('search', '')">
                {{ __('Clear') }}
            </flux:button>
        @endif
    </div>

    <div wire:loading.delay wire:target="search">
        <flux:card>
            <x-payment-gateway::table-skeleton :rows="3" :columns="5" />
        </flux:card>
    </div>

    <div wire:loading.remove.delay wire:target="search">
    @if ($gateways->isEmpty())
        <flux:card>
            <x-payment-gateway::empty-state
                icon="credit-card"
                :heading="__('No payment gateways found')"
                :description="__('Run the gateway seeder or sync configuration to register payment providers.')"
            />
        </flux:card>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Gateway') }}</flux:table.column>
                <flux:table.column>{{ __('Currencies') }}</flux:table.column>
                <flux:table.column>{{ __('Environment') }}</flux:table.column>
                <flux:table.column>{{ __('Redirect') }}</flux:table.column>
                <flux:table.column>{{ __('Enabled') }}</flux:table.column>
                <flux:table.column />
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($gateways as $gw)
                    <flux:table.row :key="$gw->code">
                        <flux:table.cell>
                            <div class="flex items-center gap-3">
                                @if (filled($gw->icon))
                                    <img src="{{ $gw->icon }}" alt="" class="h-6 w-auto max-h-6 max-w-[80px] object-contain shrink-0" onerror="this.style.display='none'" />
                                @else
                                    <div class="flex size-7 items-center justify-center rounded bg-zinc-100 dark:bg-zinc-800">
                                        <flux:icon icon="credit-card" variant="mini" class="text-zinc-500" />
                                    </div>
                                @endif
                                <div class="min-w-0">
                                    <flux:link
                                        href="{{ route(config('payment-gateway.routes.names.gateways_edit', 'admin.payment.gateways.edit'), $gw->code) }}"
                                        wire:navigate
                                        class="block truncate font-medium"
                                    >{{ $gw->label }}</flux:link>
                                    <flux:text size="sm" variant="subtle" class="block font-mono">{{ $gw->code }}</flux:text>
                                </div>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="flex flex-wrap gap-1">
                                @forelse ($gw->currencies ?? [] as $cur)
                                    <flux:badge size="sm" color="zinc">{{ $cur }}</flux:badge>
                                @empty
                                    <flux:text size="sm" variant="subtle">{{ __('None') }}</flux:text>
                                @endforelse
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge size="sm" :color="$gw->environment === 'live' || $gw->environment === 'production' ? 'emerald' : 'amber'">
                                {{ ucfirst($gw->environment ?? 'demo') }}
                            </flux:badge>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge size="sm" color="zinc">
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
                            <flux:dropdown position="bottom" align="end">
                                <flux:button icon="ellipsis-horizontal" variant="subtle" size="sm" />
                                <flux:menu>
                                    <flux:menu.item
                                        href="{{ route(config('payment-gateway.routes.names.gateways_edit', 'admin.payment.gateways.edit'), $gw->code) }}"
                                        icon="cog-6-tooth"
                                        wire:navigate
                                    >{{ __('Configure') }}</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
    </div>
</div>
