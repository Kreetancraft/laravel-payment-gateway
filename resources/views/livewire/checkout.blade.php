<div class="max-w-2xl mx-auto p-6 space-y-6">
    <flux:card class="space-y-6">
        <div class="space-y-1">
            <flux:heading size="xl">{{ __('Checkout') }}</flux:heading>
            <flux:text variant="subtle">{{ __('Choose your payment method and complete the payment securely.') }}</flux:text>
        </div>

        @if($this->appliedCouponCode)
            <flux:callout variant="success" icon="check-circle">
                <div class="flex items-center justify-between w-full">
                    <div>
                        <span>Coupon <strong>{{ $this->appliedCouponCode }}</strong> applied!</span>
                        <span class="ml-2 font-semibold text-emerald-600 dark:text-emerald-400">
                            -{{ number_format($this->appliedDiscountCents / 100, 2) }} discount
                        </span>
                        @if($this->hasFreeShipping)
                            <span class="ml-2 text-xs text-sky-600 dark:text-sky-400 font-medium">+ Free Shipping</span>
                        @endif
                    </div>
                    <flux:button wire:click="removeCoupon" size="xs" variant="ghost" icon="x-mark">
                        {{ __('Remove') }}
                    </flux:button>
                </div>
            </flux:callout>
        @endif

        @if($errorMessage)
            <flux:callout variant="danger" icon="exclamation-triangle">
                {{ $errorMessage }}
            </flux:callout>
        @endif

        @if($isRedirecting)
            <flux:callout variant="success" icon="arrow-path">
                {{ __('Redirecting to payment gateway, please wait…') }}
            </flux:callout>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <flux:field>
                <flux:label>{{ __('Amount') }}</flux:label>
                <flux:input
                    type="text"
                    wire:model.live.debounce.300ms="amountInput"
                    placeholder="100.00"
                    autocomplete="off"
                />
                <flux:error name="amountInput" />
                <flux:description>{{ __('Enter amount (e.g. 100.00).') }}</flux:description>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Currency') }}</flux:label>
                <flux:input
                    type="text"
                    wire:model.live="currencyInput"
                    placeholder="USD"
                    maxlength="3"
                    autocomplete="off"
                />
                <flux:error name="currencyInput" />
            </flux:field>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <flux:field>
                <flux:label>{{ __('Email') }}</flux:label>
                <flux:input
                    type="email"
                    wire:model="customerEmail"
                    placeholder="you@example.com"
                />
                <flux:error name="customerEmail" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Name') }}</flux:label>
                <flux:input
                    type="text"
                    wire:model="customerName"
                    placeholder="John Doe"
                />
                <flux:error name="customerName" />
            </flux:field>
        </div>

        <flux:field>
            <flux:label>{{ __('Phone (optional)') }}</flux:label>
            <flux:input
                type="text"
                wire:model="customerPhone"
                placeholder="+977-9801234567"
            />
        </flux:field>

        {{-- Gateway Selector without JS TypeError --}}
        <flux:field>
            <flux:label>{{ __('Select payment gateway') }}</flux:label>
            <div class="space-y-3">
                @forelse($this->enabledGateways as $gw)
                    <button
                        type="button"
                        wire:key="gw-{{ $gw['code'] }}"
                        wire:click="$set('selectedGateway', '{{ $gw['code'] }}')"
                        class="w-full flex items-center gap-3.5 rounded-lg border p-3.5 text-start transition {{ $selectedGateway === $gw['code'] ? 'border-zinc-900 bg-zinc-50 dark:border-zinc-100 dark:bg-zinc-800 ring-1 ring-zinc-900 dark:ring-zinc-100' : 'border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}"
                    >
                        <div class="flex size-4 shrink-0 items-center justify-center rounded-full border {{ $selectedGateway === $gw['code'] ? 'border-zinc-900 dark:border-zinc-100' : 'border-zinc-400 dark:border-zinc-600' }}">
                            @if($selectedGateway === $gw['code'])
                                <div class="size-2 rounded-full bg-zinc-900 dark:bg-zinc-100"></div>
                            @endif
                        </div>

                        @if(filled($gw['icon']))
                            <div class="flex h-7 w-12 shrink-0 items-center justify-center">
                                <img src="{{ $gw['icon'] }}" alt="" class="h-full w-full object-contain" onerror="this.style.display='none'" />
                            </div>
                        @else
                            <div class="flex size-7 shrink-0 items-center justify-center rounded bg-zinc-100 dark:bg-zinc-800">
                                <flux:icon icon="credit-card" class="size-4 text-zinc-500" />
                            </div>
                        @endif

                        <div class="min-w-0 flex-1">
                            <span class="font-medium text-sm block truncate">{{ $gw['label'] }}</span>
                        </div>

                        <span class="text-xs text-zinc-500 font-mono">{{ implode(', ', $gw['currencies']) }}</span>

                        @if(!empty($gw['checkout_redirect']))
                            <flux:badge size="sm" color="zinc">{{ __('Hosted Redirect') }}</flux:badge>
                        @endif
                    </button>
                @empty
                    <div class="p-4 rounded-lg bg-amber-50 text-amber-800 dark:bg-amber-950/20 dark:text-amber-300 text-sm">
                        {{ __('No payment gateways are currently enabled.') }}
                    </div>
                @endforelse
            </div>
            <flux:error name="selectedGateway" />
        </flux:field>

        <div class="flex items-center justify-between pt-4 border-t border-zinc-200 dark:border-zinc-800">
            <div class="text-lg font-bold">
                {{ __('Total:') }} {{ strtoupper($this->currencyInput ?: 'USD') }} {{ number_format($this->finalAmountCents / 100, 2) }}
            </div>

            <flux:button
                type="button"
                variant="primary"
                wire:click="charge"
                wire:loading.attr="disabled"
                wire:target="charge"
                class="px-6 font-semibold"
            >
                <span wire:loading.remove wire:target="charge">
                    {{ __('Pay :amount', ['amount' => number_format($this->finalAmountCents / 100, 2)]) }}
                </span>
                <span wire:loading wire:target="charge" class="flex items-center gap-2">
                    <flux:icon icon="arrow-path" class="animate-spin size-4" />
                    {{ __('Processing…') }}
                </span>
            </flux:button>
        </div>

        @if(session()->has('payment_success'))
            <flux:callout variant="success" icon="check-circle">
                {{ __('Payment initiated:') }} {{ session('payment_success') }}
            </flux:callout>
        @endif
    </flux:card>
</div>