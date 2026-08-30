<div class="space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ \Kreetancraft\PaymentGateway\Layout::home() }}" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Coupons') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="space-y-2">
            <flux:heading size="xl" level="1">{{ __('Discount Coupons') }}</flux:heading>
            <flux:subheading class="max-w-xl">{{ __('Create and manage promotional codes, percentage discounts, and free shipping vouchers.') }}</flux:subheading>

            <div class="flex flex-wrap items-center gap-2 pt-1">
                <flux:badge size="sm" color="zinc" icon="ticket">
                    {{ __(':count total', ['count' => $totalCount]) }}
                </flux:badge>
                <flux:badge size="sm" color="emerald" icon="check-circle">
                    {{ __(':count active', ['count' => $activeCount]) }}
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

            <flux:button
                href="{{ route(config('payment-gateway.routes.names.coupons_create', 'admin.payment.coupons.create')) }}"
                icon="plus"
                variant="primary"
                wire:navigate
            >
                {{ __('Create coupon') }}
            </flux:button>
        </div>
    </div>

    <flux:separator variant="subtle" />

    {{-- Filters --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="relative w-full sm:max-w-xs">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('Search code or name…') }}"
                icon="magnifying-glass"
            />
            <div
                wire:loading.flex
                wire:target="search, typeFilter, statusFilter, sort"
                class="absolute inset-y-0 right-3 items-center"
            >
                <flux:icon icon="arrow-path" variant="mini" class="animate-spin opacity-50" />
            </div>
        </div>

        <flux:select wire:model.live="typeFilter" class="sm:w-44">
            <flux:select.option value="">{{ __('All types') }}</flux:select.option>
            <flux:select.option value="percentage">{{ __('Percentage') }}</flux:select.option>
            <flux:select.option value="fixed">{{ __('Fixed Amount') }}</flux:select.option>
            <flux:select.option value="buy_x_get_y">{{ __('Buy X Get Y') }}</flux:select.option>
            <flux:select.option value="free_shipping">{{ __('Free Shipping') }}</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="statusFilter" class="sm:w-44">
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
            <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
        </flux:select>

        @if ($search || $typeFilter || $statusFilter)
            <flux:button variant="subtle" size="sm" icon="x-mark" wire:click="clearFilters">
                {{ __('Clear') }}
            </flux:button>
        @endif
    </div>

    <div wire:loading.delay wire:target="search, typeFilter, statusFilter, sort">
        <flux:card>
            <x-payment-gateway::table-skeleton :rows="5" :columns="5" />
        </flux:card>
    </div>

    <div wire:loading.remove.delay wire:target="search, typeFilter, statusFilter, sort">
    @if ($coupons->isEmpty())
        <flux:card>
            <x-payment-gateway::empty-state
                icon="ticket"
                :heading="__('No coupons found')"
                :description="($search || $typeFilter || $statusFilter) ? __('No coupons match your current filters.') : __('Create a discount code to get started.')"
            >
                @if ($search || $typeFilter || $statusFilter)
                    <flux:button variant="subtle" size="sm" icon="x-mark" wire:click="clearFilters">
                        {{ __('Clear filters') }}
                    </flux:button>
                @else
                    <flux:button
                        href="{{ route(config('payment-gateway.routes.names.coupons_create', 'admin.payment.coupons.create')) }}"
                        icon="plus"
                        variant="primary"
                        size="sm"
                        wire:navigate
                    >
                        {{ __('Create coupon') }}
                    </flux:button>
                @endif
            </x-payment-gateway::empty-state>
        </flux:card>
    @else
        <flux:table :paginate="$coupons">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sort === 'code'" wire:click="sortBy('code')">{{ __('Code') }}</flux:table.column>
                <flux:table.column>{{ __('Discount') }}</flux:table.column>
                <flux:table.column>{{ __('Usage') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sort === 'expires_at'" wire:click="sortBy('expires_at')">{{ __('Expires') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column />
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($coupons as $coupon)
                    <flux:table.row :key="$coupon->id">
                        <flux:table.cell>
                            <div class="min-w-0">
                                <flux:link
                                    href="{{ route(config('payment-gateway.routes.names.coupons_show', 'admin.payment.coupons.show'), $coupon->id) }}"
                                    class="block truncate font-mono font-medium"
                                    wire:navigate
                                >
                                    {{ $coupon->code }}
                                </flux:link>
                                @if ($coupon->name)
                                    <flux:text size="sm" variant="subtle" class="block truncate">{{ $coupon->name }}</flux:text>
                                @endif
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            @if ($coupon->type === 'percentage')
                                <flux:badge size="sm" color="zinc">{{ $coupon->value }}% OFF</flux:badge>
                            @elseif ($coupon->type === 'fixed')
                                <flux:badge size="sm" color="zinc">${{ number_format($coupon->value / 100, 2) }} OFF</flux:badge>
                            @elseif ($coupon->type === 'free_shipping')
                                <flux:badge size="sm" color="emerald">{{ __('Free Shipping') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">{{ ucfirst($coupon->type) }}</flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:text size="sm" variant="subtle">
                                {{ $coupon->usage_count }}{{ $coupon->usage_limit ? ' / ' . $coupon->usage_limit : '' }}
                            </flux:text>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:text size="sm" variant="subtle">
                                {{ $coupon->expires_at?->format('M j, Y') ?? __('Never') }}
                            </flux:text>
                        </flux:table.cell>

                        <flux:table.cell>
                            @if ($coupon->is_active && (! $coupon->expires_at || $coupon->expires_at->isFuture()))
                                <flux:badge size="sm" color="emerald" icon="check-circle">{{ __('Active') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">{{ __('Inactive') }}</flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell align="end">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button icon="ellipsis-horizontal" variant="subtle" size="sm" />
                                <flux:menu>
                                    <flux:menu.item
                                        href="{{ route(config('payment-gateway.routes.names.coupons_show', 'admin.payment.coupons.show'), $coupon->id) }}"
                                        icon="eye"
                                        wire:navigate
                                    >{{ __('View Details') }}</flux:menu.item>
                                    <flux:menu.item
                                        href="{{ route(config('payment-gateway.routes.names.coupons_edit', 'admin.payment.coupons.edit'), $coupon->id) }}"
                                        icon="pencil-square"
                                        wire:navigate
                                    >{{ __('Edit') }}</flux:menu.item>
                                    <flux:menu.item
                                        wire:click="duplicate({{ $coupon->id }})"
                                        icon="document-duplicate"
                                    >{{ __('Duplicate') }}</flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item
                                        wire:click="delete({{ $coupon->id }})"
                                        icon="trash"
                                        variant="danger"
                                        wire:confirm="{{ __('Are you sure you want to delete coupon [:code]?', ['code' => $coupon->code]) }}"
                                    >{{ __('Delete') }}</flux:menu.item>
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