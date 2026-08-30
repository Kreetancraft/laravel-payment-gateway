<div class="space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ \Kreetancraft\PaymentGateway\Layout::home() }}" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Transactions') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="space-y-2">
            <flux:heading size="xl" level="1">{{ __('Payment Transactions') }}</flux:heading>
            <flux:subheading class="max-w-xl">{{ __('Audit log of all checkout charges, gateway authorization responses, and refund settlements.') }}</flux:subheading>

            <div class="flex flex-wrap items-center gap-2 pt-1">
                <flux:badge size="sm" color="zinc" icon="banknotes">
                    {{ __(':count total', ['count' => $totalCount]) }}
                </flux:badge>
                <flux:badge size="sm" color="emerald" icon="check-circle">
                    {{ __(':count successful', ['count' => $succeededCount]) }}
                </flux:badge>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <flux:button
                wire:click="exportCsv"
                icon="arrow-down-tray"
                variant="subtle"
                size="sm"
            >
                {{ __('Export CSV') }}
            </flux:button>
        </div>
    </div>

    <flux:separator variant="subtle" />

    {{-- Filters --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="relative w-full sm:max-w-xs">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('Search reference or email…') }}"
                icon="magnifying-glass"
            />
            <div
                wire:loading.flex
                wire:target="search, gatewayFilter, statusFilter, sort"
                class="absolute inset-y-0 right-3 items-center"
            >
                <flux:icon icon="arrow-path" variant="mini" class="animate-spin opacity-50" />
            </div>
        </div>

        <flux:select wire:model.live="gatewayFilter" class="sm:w-44">
            <flux:select.option value="">{{ __('All gateways') }}</flux:select.option>
            <flux:select.option value="stripe">{{ __('Stripe') }}</flux:select.option>
            <flux:select.option value="himalayan">{{ __('Himalayan Bank') }}</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="statusFilter" class="sm:w-44">
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            <flux:select.option value="succeeded">{{ __('Succeeded') }}</flux:select.option>
            <flux:select.option value="completed">{{ __('Completed') }}</flux:select.option>
            <flux:select.option value="pending">{{ __('Pending') }}</flux:select.option>
            <flux:select.option value="failed">{{ __('Failed') }}</flux:select.option>
            <flux:select.option value="refunded">{{ __('Refunded') }}</flux:select.option>
        </flux:select>

        @if ($search || $gatewayFilter || $statusFilter)
            <flux:button variant="subtle" size="sm" icon="x-mark" wire:click="clearFilters">
                {{ __('Clear') }}
            </flux:button>
        @endif
    </div>

    <div wire:loading.delay wire:target="search, gatewayFilter, statusFilter, sort">
        <flux:card>
            <x-payment-gateway::table-skeleton :rows="5" :columns="5" />
        </flux:card>
    </div>

    <div wire:loading.remove.delay wire:target="search, gatewayFilter, statusFilter, sort">
    @if ($payments->isEmpty())
        <flux:card>
            <x-payment-gateway::empty-state
                icon="banknotes"
                :heading="__('No payment transactions found')"
                :description="($search || $gatewayFilter || $statusFilter) ? __('No transactions match your current filters.') : __('Transactions will appear here as customers complete checkouts.')"
            />
        </flux:card>
    @else
        <flux:table :paginate="$payments">
            <flux:table.columns>
                <flux:table.column>{{ __('Reference') }}</flux:table.column>
                <flux:table.column>{{ __('Gateway') }}</flux:table.column>
                <flux:table.column>{{ __('Customer') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sort === 'amount_cents'" wire:click="sortBy('amount_cents')">{{ __('Amount') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sort === 'created_at'" wire:click="sortBy('created_at')">{{ __('Date') }}</flux:table.column>
                <flux:table.column />
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($payments as $payment)
                    <flux:table.row :key="$payment->id">
                        <flux:table.cell>
                            <span class="font-mono font-medium text-xs">{{ $payment->reference }}</span>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge size="sm" color="zinc">{{ ucfirst($payment->gateway) }}</flux:badge>
                        </flux:table.cell>

                        <flux:table.cell>
                            <span class="text-sm">{{ $payment->customer_email ?? '—' }}</span>
                        </flux:table.cell>

                        <flux:table.cell>
                            <span class="font-medium text-sm">
                                {{ strtoupper($payment->currency) }} {{ number_format($payment->amount_cents / 100, 2) }}
                            </span>
                        </flux:table.cell>

                        <flux:table.cell>
                            @php($st = $payment->status instanceof \BackedEnum ? $payment->status->value : (string) $payment->status)
                            @if (in_array($st, ['succeeded', 'completed'], true))
                                <flux:badge size="sm" color="emerald" icon="check-circle">{{ __('Succeeded') }}</flux:badge>
                            @elseif ($st === 'refunded')
                                <flux:badge size="sm" color="amber">{{ __('Refunded') }}</flux:badge>
                            @elseif ($st === 'pending')
                                <flux:badge size="sm" color="sky">{{ __('Pending') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">{{ ucfirst($st) }}</flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:text size="sm" variant="subtle">{{ $payment->created_at->diffForHumans() }}</flux:text>
                        </flux:table.cell>

                        <flux:table.cell align="end">
                            @if (in_array($st, ['succeeded', 'completed'], true))
                                @can('refund', $payment)
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button icon="ellipsis-horizontal" variant="subtle" size="sm" />
                                        <flux:menu>
                                            <flux:menu.item
                                                wire:click="refund({{ $payment->id }})"
                                                icon="arrow-path-rounded-square"
                                                variant="danger"
                                                wire:confirm="{{ __('Are you sure you want to refund payment [:ref]?', ['ref' => $payment->reference]) }}"
                                            >{{ __('Refund') }}</flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                @endcan
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
    </div>
</div>
