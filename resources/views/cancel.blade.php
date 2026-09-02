{{--
    The buyer backed out on the provider's page. Nothing was charged.

    Same retry link as the failed page: it carries the payable so returning to
    checkout does not mean starting over.
--}}
@php
    $payment = $payment ?? null;
    $reference = $payment?->reference ?: (request('reference') ?: request('order'));

    $checkoutRoute = config('payment-gateway.routes.names.checkout', 'payment.checkout');
    $alias = $payment?->payableAlias();

    $retryUrl = Route::has($checkoutRoute)
        ? route($checkoutRoute, array_filter([
            'payableType' => $alias,
            'payableId' => $alias ? $payment?->payable_id : null,
        ]))
        : null;
@endphp

<x-payment-gateway::layout :title="__('Payment Cancelled')">
    <flux:card class="space-y-6 text-center border-2 border-amber-500/20">
        <div class="flex size-16 mx-auto items-center justify-center rounded-full bg-amber-100 text-amber-600 dark:bg-amber-950 dark:text-amber-400">
            <flux:icon icon="x-circle" class="size-9" />
        </div>

        <div class="space-y-1">
            <flux:heading size="xl">{{ __('Payment cancelled') }}</flux:heading>
            <flux:text variant="subtle">
                {{ __('You cancelled before paying. Nothing has been charged.') }}
            </flux:text>
        </div>

        @if ($payment)
            <div class="grid grid-cols-2 gap-4 text-start">
                <div>
                    <flux:text size="sm" variant="subtle">{{ __('Reference') }}</flux:text>
                    <div class="font-mono text-sm mt-0.5 break-all">{{ $payment->reference }}</div>
                </div>
                <div>
                    <flux:text size="sm" variant="subtle">{{ __('Amount') }}</flux:text>
                    <div class="text-sm mt-0.5">
                        {{ strtoupper($payment->currency) }} {{ number_format($payment->amount_cents / 100, 2) }}
                    </div>
                </div>
            </div>
        @elseif (filled($reference))
            <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800/60 p-3 text-xs font-mono text-zinc-500 border border-zinc-200 dark:border-zinc-700">
                <span>{{ __('Reference:') }}</span> {{ $reference }}
            </div>
        @endif

        <flux:separator variant="subtle" />

        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
            @if ($retryUrl)
                <flux:button :href="$retryUrl" variant="primary" icon="arrow-path">
                    {{ __('Back to checkout') }}
                </flux:button>
            @endif

            <flux:button href="/" variant="subtle" icon="home">{{ __('Return home') }}</flux:button>
        </div>
    </flux:card>
</x-payment-gateway::layout>
