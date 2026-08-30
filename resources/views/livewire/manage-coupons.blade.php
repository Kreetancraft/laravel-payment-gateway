<div class="space-y-6">
    <x-payment-gateway::page-header
        :title="__('Discount Coupons')"
        :subtitle="__('Create and manage discount codes, promotional vouchers, and free shipping rules.')"
    >
        <x-slot:breadcrumbs>
            <flux:breadcrumbs>
                <flux:breadcrumbs.item href="{{ \Kreetancraft\PaymentGateway\Layout::home() }}" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>{{ __('Coupons') }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>
        </x-slot:breadcrumbs>

        <x-slot:badges>
            <flux:badge size="sm" color="zinc" icon="ticket">
                {{ __(':count total', ['count' => $totalCount]) }}
            </flux:badge>
            <flux:badge size="sm" color="green" icon="check-circle">
                {{ __(':count active', ['count' => $activeCount]) }}
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

            <flux:button
                href="{{ route(config('payment-gateway.routes.names.coupons_create', 'admin.payment.coupons.create')) }}"
                icon="plus"
                variant="primary"
                size="sm"
                wire:navigate
            >
                {{ __('Create Coupon') }}
            </flux:button>
        </x-slot:actions>
    </x-payment-gateway::page-header>

    <flux:separator variant="subtle" />

    @if (session()->has('coupon_message'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('coupon_message') }}
        </flux:callout>
    @endif

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <flux:card class="p-4">
            <flux:text class="text-xs text-zinc-500">{{ __('Total Redemptions') }}</flux:text>
            <flux:heading size="xl" class="mt-1">{{ number_format($totalRedemptions) }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-xs text-zinc-500">{{ __('Total Discount Given') }}</flux:text>
            <flux:heading size="xl" class="mt-1">${{ number_format($totalDiscountCents / 100, 2) }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-xs text-zinc-500">{{ __('Active Coupons') }}</flux:text>
            <flux:heading size="xl" class="mt-1 text-emerald-600 dark:text-emerald-400">{{ $activeCount }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-xs text-zinc-500">{{ __('Total Created') }}</flux:text>
            <flux:heading size="xl" class="mt-1">{{ $totalCount }}</flux:heading>
        </flux:card>
    </div>

    {{-- Filters --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="relative w-full sm:max-w-xs">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('Search code or name…') }}"
                icon="magnifying-glass"
                size="sm"
            />
            <div wire:loading wire:target="search" class="absolute inset-y-0 right-3 flex items-center">
                <flux:icon icon="arrow-path" variant="mini" class="animate-spin opacity-50" />
            </div>
        </div>

        <flux:select wire:model.live="typeFilter" size="sm" class="sm:w-44">
            <flux:select.option value="">{{ __('All types') }}</flux:select.option>
            <flux:select.option value="percentage">{{ __('Percentage') }}</flux:select.option>
            <flux:select.option value="fixed">{{ __('Fixed Amount') }}</flux:select.option>
            <flux:select.option value="buy_x_get_y">{{ __('Buy X Get Y') }}</flux:select.option>
            <flux:select.option value="free_shipping">{{ __('Free Shipping') }}</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="statusFilter" size="sm" class="sm:w-40">
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
            <flux:select.option value="inactive">{{ __('Inactive / Expired') }}</flux:select.option>
        </flux:select>

        @if ($search || $typeFilter || $statusFilter)
            <flux:button variant="subtle" size="sm" icon="x-mark" wire:click="clearFilters">
                {{ __('Clear') }}
            </flux:button>
        @endif
    </div>

    @if ($coupons->isEmpty())
        <x-payment-gateway::empty-state
            icon="ticket"
            :title="__('No discount coupons found')"
            :description="__('Create a discount code to start offering promotional pricing.')"
        >
            <flux:button
                href="{{ route(config('payment-gateway.routes.names.coupons_create', 'admin.payment.coupons.create')) }}"
                icon="plus"
                variant="primary"
                size="sm"
                wire:navigate
            >
                {{ __('Create Coupon') }}
            </flux:button>
        </x-payment-gateway::empty-state>
    @else
        <flux:card class="overflow-hidden p-0">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Code') }}</flux:table.column>
                    <flux:table.column>{{ __('Discount') }}</flux:table.column>
                    <flux:table.column>{{ __('Usage') }}</flux:table.column>
                    <flux:table.column>{{ __('Expires') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($coupons as $coupon)
                        <flux:table.row wire:key="coupon-{{ $coupon->id }}">
                            <flux:table.cell>
                                <div>
                                    <flux:link
                                        href="{{ route(config('payment-gateway.routes.names.coupons_show', 'admin.payment.coupons.show'), $coupon->id) }}"
                                        class="font-mono font-semibold"
                                        wire:navigate
                                    >
                                        {{ $coupon->code }}
                                    </flux:link>
                                    @if ($coupon->name)
                                        <div class="text-xs text-zinc-500">{{ $coupon->name }}</div>
                                    @endif
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($coupon->type === 'percentage')
                                    <flux:badge size="sm" color="violet">{{ $coupon->value }}% OFF</flux:badge>
                                @elseif ($coupon->type === 'fixed')
                                    <flux:badge size="sm" color="blue">${{ number_format($coupon->value / 100, 2) }} OFF</flux:badge>
                                @elseif ($coupon->type === 'free_shipping')
                                    <flux:badge size="sm" color="emerald">{{ __('Free Shipping') }}</flux:badge>
                                @else
                                    <flux:badge size="sm" color="zinc">{{ ucfirst($coupon->type) }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <span class="text-sm font-medium">{{ $coupon->usage_count }}</span>
                                <span class="text-xs text-zinc-500">/ {{ $coupon->usage_limit ?? '∞' }}</span>
                            </flux:table.cell>
                            <flux:table.cell>
                                <span class="text-xs text-zinc-500">
                                    {{ $coupon->expires_at?->format('M j, Y') ?? __('Never') }}
                                </span>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($coupon->is_active && (! $coupon->expires_at || $coupon->expires_at->isFuture()))
                                    <flux:badge size="sm" color="green">{{ __('Active') }}</flux:badge>
                                @else
                                    <flux:badge size="sm" color="zinc">{{ __('Inactive') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:dropdown>
                                    <flux:button icon="ellipsis-vertical" variant="ghost" size="sm" />
                                    <flux:menu>
                                        <flux:menu.item
                                            href="{{ route(config('payment-gateway.routes.names.coupons_show', 'admin.payment.coupons.show'), $coupon->id) }}"
                                            icon="eye"
                                            wire:navigate
                                        >
                                            {{ __('View Details') }}
                                        </flux:menu.item>
                                        <flux:menu.item
                                            href="{{ route(config('payment-gateway.routes.names.coupons_edit', 'admin.payment.coupons.edit'), $coupon->id) }}"
                                            icon="pencil-square"
                                            wire:navigate
                                        >
                                            {{ __('Edit') }}
                                        </flux:menu.item>
                                        <flux:menu.item
                                            wire:click="duplicate({{ $coupon->id }})"
                                            icon="document-duplicate"
                                        >
                                            {{ __('Duplicate') }}
                                        </flux:menu.item>
                                        <flux:menu.separator />
                                        <flux:menu.item
                                            wire:click="delete({{ $coupon->id }})"
                                            icon="trash"
                                            variant="danger"
                                            wire:confirm="{{ __('Are you sure you want to delete coupon [:code]?', ['code' => $coupon->code]) }}"
                                        >
                                            {{ __('Delete') }}
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <div class="p-4 border-t border-zinc-200 dark:border-zinc-800">
                {{ $coupons->links() }}
            </div>
        </flux:card>
    @endif
</div>