@props(['active' => null])

@php
    $checkoutRoute = config('payment-gateway.routes.names.checkout', 'payment.checkout');
    $successRoute = config('payment-gateway.routes.names.success', 'payment.success');
    $hasCheckout = \Illuminate\Support\Facades\Route::has($checkoutRoute);
    $hasSuccess = \Illuminate\Support\Facades\Route::has($successRoute);
@endphp

<nav {{ $attributes->merge(['class' => 'flex items-center gap-1']) }}>
    @if($hasCheckout)
        <flux:navlist.item
            :href="route($checkoutRoute)"
            :current="request()->routeIs($checkoutRoute)"
            icon="credit-card"
        >
            {{ __('Checkout') }}
        </flux:navlist.item>
    @endif

    @if($hasSuccess)
        <flux:navlist.item
            :href="route($successRoute)"
            :current="request()->routeIs($successRoute)"
            icon="check-circle"
        >
            {{ __('Payments') }}
        </flux:navlist.item>
    @endif

    <flux:dropdown>
        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
        <flux:menu>
            <flux:menu.item :href="route($checkoutRoute, ['gateway' => 'stripe'])" wire:navigate icon="building-library">Stripe</flux:menu.item>
            <flux:menu.item :href="route($checkoutRoute, ['gateway' => 'himalayan'])" wire:navigate icon="globe-alt">Himalayan Bank</flux:menu.item>
        </flux:menu>
    </flux:dropdown>
</nav>
