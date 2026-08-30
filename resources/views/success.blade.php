<x-payment-gateway::layout :title="__('Payment Successful')">
    <flux:card class="space-y-6 border-2 border-emerald-500/30">
        <div class="flex items-center gap-4">
            <div class="flex size-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400">
                <flux:icon icon="check-circle" class="size-8" />
            </div>
            <div>
                <flux:heading size="xl">{{ __('Payment Successful!') }}</flux:heading>
                <flux:text variant="subtle">{{ __('Your payment has been successfully processed and verified.') }}</flux:text>
            </div>
        </div>

        <flux:separator variant="subtle" />

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <div>
                <flux:text size="sm" variant="subtle">{{ __('Order Reference') }}</flux:text>
                <div class="font-mono font-semibold text-sm mt-0.5">
                    {{ $result->reference ?? $result->transactionId ?? request('order') ?? request('orderNo') ?? request('reference') ?? '—' }}
                </div>
            </div>

            @if(isset($result->transactionId) && filled($result->transactionId))
                <div>
                    <flux:text size="sm" variant="subtle">{{ __('Transaction ID') }}</flux:text>
                    <div class="font-mono text-sm mt-0.5 truncate">{{ $result->transactionId }}</div>
                </div>
            @endif

            <div>
                <flux:text size="sm" variant="subtle">{{ __('Status') }}</flux:text>
                <div class="mt-0.5">
                    <flux:badge size="sm" color="emerald" icon="check-circle">
                        {{ ucfirst($result->status ?? 'Paid') }}
                    </flux:badge>
                </div>
            </div>

            @if(isset($result->amount) && $result->amount > 0)
                <div>
                    <flux:text size="sm" variant="subtle">{{ __('Amount Paid') }}</flux:text>
                    <div class="font-bold text-lg text-emerald-600 dark:text-emerald-400 mt-0.5">
                        {{ strtoupper($result->currency ?? 'USD') }} {{ number_format($result->amount, 2) }}
                    </div>
                </div>
            @endif

            <div>
                <flux:text size="sm" variant="subtle">{{ __('Date') }}</flux:text>
                <div class="text-sm mt-0.5">{{ now()->format('M j, Y H:i') }}</div>
            </div>
        </div>

        @if(isset($result->errorMessage) && filled($result->errorMessage))
            <flux:callout variant="warning" icon="exclamation-triangle">
                {{ $result->errorMessage }}
            </flux:callout>
        @endif

        <flux:separator variant="subtle" />

        <div class="flex items-center justify-between pt-2">
            <flux:button href="/" variant="subtle" icon="home">
                {{ __('Return Home') }}
            </flux:button>

            @if(Route::has('admin.payment.transactions'))
                <flux:button :href="route('admin.payment.transactions')" variant="primary" icon="banknotes">
                    {{ __('View in Transactions') }}
                </flux:button>
            @endif
        </div>
    </flux:card>
</x-payment-gateway::layout>
