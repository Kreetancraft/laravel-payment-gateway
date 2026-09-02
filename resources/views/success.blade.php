{{--
    The page a buyer lands on after paying.

    It renders from the payment record, not the query string, and it does not
    claim more than it knows. A gateway can answer "not settled yet" — Stripe
    does exactly that while a delayed payment method clears — and that answer
    arrives here as a successful verification with a pending status. Saying
    "Payment Successful" over it tells someone their order is placed when the
    money has not arrived.
--}}
@php
    $settled = ($payment?->status?->value === 'succeeded')
        || in_array(strtolower($result->status ?? ''), ['succeeded', 'completed', 'paid', 'success'], true);

    $reference = $payment?->reference
        ?: ($result->reference ?: ($result->transactionId ?: request('reference')));

    $amount = $payment ? $payment->amount_cents / 100 : ($result->amount ?? null);
    $currency = strtoupper($payment?->currency ?: ($result->currency ?: ''));
    $paidAt = $payment?->paid_at;
@endphp

<x-payment-gateway::layout :title="$settled ? __('Payment Successful') : __('Payment Received')">
    <flux:card class="space-y-6 border-2 {{ $settled ? 'border-emerald-500/30' : 'border-amber-500/30' }}">
        <div class="flex items-center gap-4">
            <div @class([
                'flex size-14 shrink-0 items-center justify-center rounded-full',
                'bg-emerald-100 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400' => $settled,
                'bg-amber-100 text-amber-600 dark:bg-amber-950 dark:text-amber-400' => ! $settled,
            ])>
                <flux:icon :icon="$settled ? 'check-circle' : 'clock'" class="size-8" />
            </div>
            <div>
                <flux:heading size="xl">
                    {{ $settled ? __('Payment successful') : __('Payment received') }}
                </flux:heading>
                <flux:text variant="subtle">
                    {{ $settled
                        ? __('Your payment has been confirmed by the provider.')
                        : __('We have your payment and are waiting for the provider to confirm it. You do not need to pay again.') }}
                </flux:text>
            </div>
        </div>

        <flux:separator variant="subtle" />

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <div>
                <flux:text size="sm" variant="subtle">{{ __('Reference') }}</flux:text>
                <div class="font-mono font-semibold text-sm mt-0.5 break-all">{{ $reference ?: '—' }}</div>
            </div>

            @if ($amount !== null && $amount > 0)
                <div>
                    <flux:text size="sm" variant="subtle">{{ __('Amount') }}</flux:text>
                    <div @class([
                        'font-bold text-lg mt-0.5',
                        'text-emerald-600 dark:text-emerald-400' => $settled,
                    ])>
                        {{ $currency }} {{ number_format($amount, 2) }}
                    </div>
                </div>
            @endif

            <div>
                <flux:text size="sm" variant="subtle">{{ __('Status') }}</flux:text>
                <div class="mt-0.5">
                    <flux:badge size="sm" :color="$settled ? 'emerald' : 'amber'" :icon="$settled ? 'check-circle' : 'clock'">
                        {{ $settled ? __('Paid') : __('Confirming') }}
                    </flux:badge>
                </div>
            </div>

            @if ($payment?->description)
                <div class="col-span-2 sm:col-span-3">
                    <flux:text size="sm" variant="subtle">{{ __('For') }}</flux:text>
                    <div class="text-sm mt-0.5">{{ $payment->description }}</div>
                </div>
            @endif

            @if ($payment?->gateway)
                <div>
                    <flux:text size="sm" variant="subtle">{{ __('Paid with') }}</flux:text>
                    <div class="text-sm mt-0.5">{{ ucfirst($payment->gateway) }}</div>
                </div>
            @endif

            {{-- When the payment settled, not when this page happened to be opened. --}}
            @if ($paidAt)
                <div>
                    <flux:text size="sm" variant="subtle">{{ __('Date') }}</flux:text>
                    <div class="text-sm mt-0.5">{{ $paidAt->format('M j, Y H:i') }}</div>
                </div>
            @endif

            @if ($payment?->customer_email)
                <div class="col-span-2">
                    <flux:text size="sm" variant="subtle">{{ __('Confirmation for') }}</flux:text>
                    <div class="text-sm mt-0.5 break-all">{{ $payment->customer_email }}</div>
                </div>
            @endif
        </div>

        @unless ($settled)
            <flux:callout variant="warning" icon="information-circle">
                {{ __('Keep this reference. If anything looks wrong, quote it rather than starting a new payment.') }}
            </flux:callout>
        @endunless

        <flux:separator variant="subtle" />

        <div class="flex items-center justify-between pt-2">
            <flux:button href="/" variant="subtle" icon="home">{{ __('Return home') }}</flux:button>

            @if (Route::has('admin.payment.transactions'))
                <flux:button :href="route('admin.payment.transactions')" variant="primary" icon="banknotes">
                    {{ __('View in transactions') }}
                </flux:button>
            @endif
        </div>
    </flux:card>
</x-payment-gateway::layout>
