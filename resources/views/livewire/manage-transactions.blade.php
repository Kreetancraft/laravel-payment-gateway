<div class="space-y-6">
    <x-payment-gateway::page-header
        :title="__('Payment Transactions')"
        :subtitle="__('Audit log of all checkout charges, gateway authorization responses, and refund settlements.')"
    >
        <x-slot:breadcrumbs>
            <flux:breadcrumbs>
                <flux:breadcrumbs.item href="{{ \Kreetancraft\PaymentGateway\Layout::home() }}" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>{{ __('Transactions') }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>
        </x-slot:breadcrumbs>

        <x-slot:badges>
            <flux:badge size="sm" color="zinc" icon="banknotes">
                {{ __(':count total', ['count' => $totalCount]) }}
            </flux:badge>
            <flux:badge size="sm" color="green" icon="check-circle">
                {{ __(':count successful', ['count' => $succeededCount]) }}
            </flux:badge>
        </x-slot:badges>

        <x-slot:actions>
            <flux:button
                wire:click="exportCsv"
                icon="arrow-down-tray"
                variant="outline"
                size="sm"
            >
                {{ __('Export CSV') }}
            </flux:button>
        </x-slot:actions>
    </x-payment-gateway::page-header>

    <flux:separator variant="subtle" />

    @if (session()->has('transaction_message'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('transaction_message') }}
        </flux:callout>
    @endif

    @if (session()->has('transaction_error'))
        <flux:callout variant="danger" icon="exclamation-triangle">
            {{ session('transaction_error') }}
        </flux:callout>
    @endif

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
        <flux:card class="p-4">
            <flux:text class="text-xs text-zinc-500">{{ __('Total Processed Volume') }}</flux:text>
            <flux:heading size="xl" class="mt-1">${{ number_format($totalVolumeCents / 100, 2) }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-xs text-zinc-500">{{ __('Successful Transactions') }}</flux:text>
            <flux:heading size="xl" class="mt-1 text-emerald-600 dark:text-emerald-400">{{ $succeededCount }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-xs text-zinc-500">{{ __('Total Attempts') }}</flux:text>
            <flux:heading size="xl" class="mt-1">{{ $totalCount }}</flux:heading>
        </flux:card>
    </div>

    {{-- Filters --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="relative w-full sm:max-w-xs">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('Search reference or email…') }}"
                icon="magnifying-glass"
                size="sm"
            />
            <div wire:loading wire:target="search" class="absolute inset-y-0 right-3 flex items-center">
                <flux:icon icon="arrow-path" variant="mini" class="animate-spin opacity-50" />
            </div>
        </div>

        <flux:select wire:model.live="gatewayFilter" size="sm" class="sm:w-44">
            <flux:select.option value="">{{ __('All gateways') }}</flux:select.option>
            <flux:select.option value="stripe">{{ __('Stripe') }}</flux:select.option>
            <flux:select.option value="himalayan_bank">{{ __('Himalayan Bank') }}</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="statusFilter" size="sm" class="sm:w-40">
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

    @if ($payments->isEmpty())
        <x-payment-gateway::empty-state
            icon="banknotes"
            :title="__('No payment transactions found')"
            :description="__('Transactions will appear here as customers complete checkouts.')"
        />
    @else
        <flux:card class="overflow-hidden p-0">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Reference') }}</flux:table.column>
                    <flux:table.column>{{ __('Gateway') }}</flux:table.column>
                    <flux:table.column>{{ __('Customer') }}</flux:table.column>
                    <flux:table.column>{{ __('Amount') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Date') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($payments as $payment)
                        <flux:table.row wire:key="payment-{{ $payment->id }}">
                            <flux:table.cell>
                                <div class="font-mono font-medium text-xs">{{ $payment->reference }}</div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" color="zinc">{{ ucfirst($payment->gateway) }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <span class="text-sm">{{ $payment->customer_email ?? '—' }}</span>
                            </flux:table.cell>
                            <flux:table.cell>
                                <span class="font-semibold text-sm">
                                    {{ strtoupper($payment->currency) }} {{ number_format($payment->amount_cents / 100, 2) }}
                                </span>
                            </flux:table.cell>
                            <flux:table.cell>
                                @php($st = $payment->status instanceof \BackedEnum ? $payment->status->value : (string) $payment->status)
                                @if (in_array($st, ['succeeded', 'completed'], true))
                                    <flux:badge size="sm" color="green">{{ __('Succeeded') }}</flux:badge>
                                @elseif ($st === 'refunded')
                                    <flux:badge size="sm" color="amber">{{ __('Refunded') }}</flux:badge>
                                @elseif ($st === 'pending')
                                    <flux:badge size="sm" color="sky">{{ __('Pending') }}</flux:badge>
                                @else
                                    <flux:badge size="sm" color="red">{{ ucfirst($st) }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <span class="text-xs text-zinc-500">{{ $payment->created_at->format('M j, Y H:i') }}</span>
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                @if (in_array($payment->status, ['succeeded', 'completed'], true))
                                    @can('refund', $payment)
                                        <flux:button
                                            size="xs"
                                            variant="danger"
                                            icon="arrow-path-rounded-square"
                                            wire:click="refund({{ $payment->id }})"
                                            wire:confirm="{{ __('Are you sure you want to refund payment [:ref]?', ['ref' => $payment->reference]) }}"
                                        >
                                            {{ __('Refund') }}
                                        </flux:button>
                                    @endcan
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <div class="p-4 border-t border-zinc-200 dark:border-zinc-800">
                {{ $payments->links() }}
            </div>
        </flux:card>
    @endif
</div>
