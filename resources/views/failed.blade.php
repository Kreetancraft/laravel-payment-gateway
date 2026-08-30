<x-payment-gateway::layout :title="__('Payment Failed')">
    <flux:card class="space-y-6 text-center border-2 border-rose-500/20">
        <div class="flex size-16 mx-auto items-center justify-center rounded-full bg-rose-100 text-rose-600 dark:bg-rose-950 dark:text-rose-400">
            <flux:icon icon="exclamation-triangle" class="size-9" />
        </div>

        <div class="space-y-1">
            <flux:heading size="xl">{{ __('Payment Failed') }}</flux:heading>
            <flux:text variant="subtle">
                {{ $errorMessage ?? __('The transaction could not be completed by the payment provider.') }}
            </flux:text>
        </div>

        @if(isset($payload['reference']) || isset($payload['order_id']) || request('reference'))
            <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800/60 p-3 text-xs font-mono text-zinc-500 border border-zinc-200 dark:border-zinc-700">
                <span>Reference:</span> {{ $payload['reference'] ?? $payload['order_id'] ?? request('reference') }}
            </div>
        @endif

        <flux:separator variant="subtle" />

        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
            <flux:button
                :href="route(config('payment-gateway.routes.names.checkout', 'payment.checkout'))"
                variant="primary"
                icon="arrow-path"
            >
                {{ __('Try Another Payment Method') }}
            </flux:button>

            <flux:button href="/" variant="subtle" icon="home">
                {{ __('Return Home') }}
            </flux:button>
        </div>
    </flux:card>
</x-payment-gateway::layout>
