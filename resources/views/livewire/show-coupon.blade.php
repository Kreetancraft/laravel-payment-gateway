<div class="space-y-6">
    <x-payment-gateway::page-header
        :title="__('Coupon: :code', ['code' => $coupon->code])"
        :subtitle="$coupon->name ?? __('Promotional discount code')"
    >
        <x-slot:breadcrumbs>
            <flux:breadcrumbs>
                <flux:breadcrumbs.item href="{{ \Kreetancraft\PaymentGateway\Layout::home() }}" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item href="{{ route(config('payment-gateway.routes.names.coupons', 'admin.payment.coupons')) }}" wire:navigate>{{ __('Coupons') }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>{{ $coupon->code }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>
        </x-slot:breadcrumbs>

        <x-slot:badges>
            <flux:badge size="sm" :color="$coupon->is_active ? 'green' : 'zinc'">
                {{ $coupon->is_active ? __('Active') : __('Inactive') }}
            </flux:badge>
            <flux:badge size="sm" color="sky">
                {{ __(':count / :limit used', ['count' => $coupon->usage_count, 'limit' => $coupon->usage_limit ?? '∞']) }}
            </flux:badge>
        </x-slot:badges>

        <x-slot:actions>
            <flux:button
                href="{{ route(config('payment-gateway.routes.names.coupons_edit', 'admin.payment.coupons.edit'), $coupon->id) }}"
                icon="pencil-square"
                variant="primary"
                size="sm"
                wire:navigate
            >
                {{ __('Edit Coupon') }}
            </flux:button>
        </x-slot:actions>
    </x-payment-gateway::page-header>

    <flux:separator variant="subtle" />

    {{-- Metrics Grid --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <flux:card class="p-4">
            <flux:text class="text-xs text-zinc-500">{{ __('Discount Value') }}</flux:text>
            <flux:heading size="xl" class="mt-1">
                @if ($coupon->type === 'percentage')
                    {{ $coupon->value }}% OFF
                @elseif ($coupon->type === 'fixed')
                    ${{ number_format($coupon->value / 100, 2) }} OFF
                @elseif ($coupon->type === 'free_shipping')
                    {{ __('Free Shipping') }}
                @else
                    {{ $coupon->value }}
                @endif
            </flux:heading>
        </flux:card>

        <flux:card class="p-4">
            <flux:text class="text-xs text-zinc-500">{{ __('Total Redemptions') }}</flux:text>
            <flux:heading size="xl" class="mt-1">{{ $coupon->usage_count }}</flux:heading>
        </flux:card>

        <flux:card class="p-4">
            <flux:text class="text-xs text-zinc-500">{{ __('Total Discount Given') }}</flux:text>
            <flux:heading size="xl" class="mt-1">${{ number_format($totalDiscountCents / 100, 2) }}</flux:heading>
        </flux:card>

        <flux:card class="p-4">
            <flux:text class="text-xs text-zinc-500">{{ __('Validity Window') }}</flux:text>
            <flux:text class="text-sm font-medium mt-1">
                {{ $coupon->starts_at?->format('M j, Y') ?? __('Now') }} → {{ $coupon->expires_at?->format('M j, Y') ?? __('Never') }}
            </flux:text>
        </flux:card>
    </div>

    {{-- Redemption History Table --}}
    <flux:card class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="lg">{{ __('Redemption History') }}</flux:heading>
                <flux:subheading class="text-xs text-zinc-500">{{ __('Log of all orders where this coupon code was successfully applied.') }}</flux:subheading>
            </div>
        </div>

        <flux:separator variant="subtle" />

        @if ($redemptions->isEmpty())
            <flux:text class="text-sm text-zinc-500 text-center py-6">{{ __('No redemptions recorded for this coupon yet.') }}</flux:text>
        @else
            <flux:table>
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
                                <span class="text-xs text-zinc-500">{{ $redemption->created_at->format('M j, Y H:i') }}</span>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <div class="pt-3 border-t border-zinc-200 dark:border-zinc-800">
                {{ $redemptions->links() }}
            </div>
        @endif
    </flux:card>
</div>
