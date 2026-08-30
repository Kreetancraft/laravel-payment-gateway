<div class="space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ \Kreetancraft\PaymentGateway\Layout::home() }}" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item href="{{ route(config('payment-gateway.routes.names.coupons', 'admin.payment.coupons')) }}" wire:navigate>{{ __('Coupons') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $coupon->code }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="space-y-2">
            <flux:heading size="xl" level="1">{{ $coupon->code }}</flux:heading>
            <flux:subheading class="max-w-xl">{{ $coupon->name ?? __('Promotional discount code') }}</flux:subheading>

            <div class="flex flex-wrap items-center gap-2 pt-1">
                <flux:badge size="sm" :color="$coupon->is_active ? 'emerald' : 'zinc'" :icon="$coupon->is_active ? 'check-circle' : null">
                    {{ $coupon->is_active ? __('Active') : __('Inactive') }}
                </flux:badge>
                <flux:badge size="sm" color="zinc">
                    {{ __(':count / :limit used', ['count' => $coupon->usage_count, 'limit' => $coupon->usage_limit ?? '∞']) }}
                </flux:badge>
            </div>
        </div>

        <flux:button
            href="{{ route(config('payment-gateway.routes.names.coupons_edit', 'admin.payment.coupons.edit'), $coupon->id) }}"
            icon="pencil-square"
            variant="primary"
            size="sm"
            wire:navigate
        >
            {{ __('Edit coupon') }}
        </flux:button>
    </div>

    <flux:separator variant="subtle" />

    {{-- Details Section --}}
    <x-payment-gateway::form-section :title="__('Coupon Overview')">
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div>
                <flux:text size="sm" variant="subtle">{{ __('Discount Value') }}</flux:text>
                <div class="text-base font-semibold mt-1">
                    @if ($coupon->type === 'percentage')
                        {{ $coupon->value }}% OFF
                    @elseif ($coupon->type === 'fixed')
                        ${{ number_format($coupon->value / 100, 2) }} OFF
                    @elseif ($coupon->type === 'free_shipping')
                        {{ __('Free Shipping') }}
                    @else
                        {{ $coupon->value }}
                    @endif
                </div>
            </div>

            <div>
                <flux:text size="sm" variant="subtle">{{ __('Total Redemptions') }}</flux:text>
                <div class="text-base font-semibold mt-1">{{ $coupon->usage_count }}</div>
            </div>

            <div>
                <flux:text size="sm" variant="subtle">{{ __('Total Discount Given') }}</flux:text>
                <div class="text-base font-semibold mt-1">${{ number_format($totalDiscountCents / 100, 2) }}</div>
            </div>

            <div>
                <flux:text size="sm" variant="subtle">{{ __('Validity Window') }}</flux:text>
                <div class="text-sm font-medium mt-1">
                    {{ $coupon->starts_at?->format('M j, Y') ?? __('Now') }} → {{ $coupon->expires_at?->format('M j, Y') ?? __('Never') }}
                </div>
            </div>
        </div>
    </x-payment-gateway::form-section>

    {{-- Redemption History --}}
    <div class="space-y-3">
        <div>
            <flux:heading size="lg">{{ __('Redemption History') }}</flux:heading>
            <flux:subheading class="text-xs text-zinc-500">{{ __('Log of all customer orders where this coupon code was applied.') }}</flux:subheading>
        </div>

        @if ($redemptions->isEmpty())
            <flux:card>
                <x-payment-gateway::empty-state
                    icon="ticket"
                    :title="__('No redemptions yet')"
                    :description="__('Redemptions will appear here once customers apply this coupon during checkout.')"
                />
            </flux:card>
        @else
            <flux:table :paginate="$redemptions">
                <flux:table.columns>
                    <flux:table.column>{{ __('User ID') }}</flux:table.column>
                    <flux:table.column>{{ __('Order Reference') }}</flux:table.column>
                    <flux:table.column>{{ __('Discount Applied') }}</flux:table.column>
                    <flux:table.column>{{ __('Date') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($redemptions as $redemption)
                        <flux:table.row wire:key="usage-{{ $redemption->id }}">
                            <flux:table.cell>
                                <span class="font-medium text-sm">{{ $redemption->user_id ? 'User #' . $redemption->user_id : __('Guest') }}</span>
                            </flux:table.cell>
                            <flux:table.cell>
                                <span class="font-mono text-xs">{{ $redemption->order_id ?? '—' }}</span>
                            </flux:table.cell>
                            <flux:table.cell>
                                <span class="font-semibold text-sm text-emerald-600 dark:text-emerald-400">
                                    -${{ number_format($redemption->amount_discounted_cents / 100, 2) }}
                                </span>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:text size="sm" variant="subtle">{{ $redemption->created_at->format('M j, Y H:i') }}</flux:text>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </div>
</div>
