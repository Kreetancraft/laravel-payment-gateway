<div class="max-w-2xl mx-auto p-6 space-y-6">
    <flux:card class="space-y-6">
        <flux:heading size="xl">Checkout</flux:heading>
        <flux:text variant="muted">Choose your payment method and complete the payment securely.</flux:text>

        @if($this->appliedCouponCode)
            <flux:callout variant="success" icon="check-circle" class="mb-4">
                Coupon <strong>{{ $this->appliedCouponCode }}</strong> applied!
                <span class="ml-2 font-semibold text-green-600 dark:text-green-400">
                    -{{ number_format($this->appliedDiscountCents / 100, 2) }} discount
                </span>
                @if($this->hasFreeShipping)
                    <span class="ml-2 text-sm text-blue-600 dark:text-blue-400">+ Free Shipping</span>
                @endif
                <button wire:click="removeCoupon" class="ml-4 text-sm text-zinc-500 hover:text-zinc-700">Remove</button>
            </flux:callout>
        @endif

        @if($errorMessage)
            <flux:callout variant="danger" icon="exclamation-triangle">
                {{ $errorMessage }}
            </flux:callout>
        @endif

        @if($isRedirecting)
            <flux:callout variant="success" icon="arrow-path">
                Redirecting to payment gateway, please wait...
            </flux:callout>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <flux:field>
                <flux:label>Amount</flux:label>
                <flux:input
                    type="text"
                    wire:model.live="amountInput"
                    placeholder="100.00"
                    autocomplete="off"
                />
                <flux:error name="amountInput" />
                <flux:description>Enter amount in major units (e.g. 100.00).</flux:description>
            </flux:field>

            <flux:field>
                <flux:label>Currency</flux:label>
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
                <flux:label>Email</flux:label>
                <flux:input
                    type="email"
                    wire:model="customerEmail"
                    placeholder="you@example.com"
                />
                <flux:error name="customerEmail" />
            </flux:field>

            <flux:field>
                <flux:label>Name</flux:label>
                <flux:input
                    type="text"
                    wire:model="customerName"
                    placeholder="John Doe"
                />
                <flux:error name="customerName" />
            </flux:field>
        </div>

        <flux:field>
            <flux:label>Phone (optional)</flux:label>
            <flux:input
                type="text"
                wire:model="customerPhone"
                placeholder="+977-9XXXXXXXX"
            />
        </flux:field>

        @if(count($this->enabledGateways) > 1)
            <flux:field>
                <flux:label>Select payment gateway</flux:label>
                <div class="space-y-3">
                    @foreach($this->enabledGateways as $gw)
                        <label wire:key="gw-{{ $gw['code'] }}" class="flex items-center gap-3 rounded-xl border px-4 py-3 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800 transition {{ $selectedGateway === $gw['code'] ? 'border-zinc-900 dark:border-zinc-100 bg-zinc-50 dark:bg-zinc-800' : 'border-zinc-200 dark:border-zinc-700' }}">
                            <flux:radio wire:model.live="selectedGateway" value="{{ $gw['code'] }}" />
                            @if(filled($gw['icon']))
                                <img src="{{ $gw['icon'] }}" alt="{{ $gw['label'] }}" class="h-6 w-auto object-contain" />
                            @endif
                            <span class="font-medium text-sm">{{ $gw['label'] }}</span>
                            <span class="ml-auto text-xs text-zinc-500">{{ implode(', ', $gw['currencies']) }}</span>
                        </label>
                    @endforeach
                </div>
                <flux:error name="selectedGateway" />
            </flux:field>
        @else
            @foreach($this->enabledGateways as $gw)
                <div wire:key="single-{{ $gw['code'] }}" class="flex items-center gap-3 rounded-xl border border-zinc-200 dark:border-zinc-700 px-4 py-3 bg-zinc-50 dark:bg-zinc-800">
                    @if(filled($gw['icon']))
                        <img src="{{ $gw['icon'] }}" alt="{{ $gw['label'] }}" class="h-6 w-auto object-contain" />
                    @endif
                    <span class="font-medium text-sm">{{ $gw['label'] }}</span>
                    <flux:badge variant="pill" color="zinc" class="ml-auto">{{ $gw['code'] }}</flux:badge>
                </div>
            @endforeach
        @endif

        <div class="flex justify-end gap-3">
            <flux:button
                variant="primary"
                wire:click="charge"
                wire:loading.attr="disabled"
                icon="credit-card"
            >
                <span wire:loading.remove wire:target="charge">Pay {{ number_format($finalAmountCents() / 100, 2) }}</span>
                <span wire:loading wire:target="charge">Processing...</span>
            </flux:button>
        </div>

        @if(session()->has('payment_success'))
            <flux:callout variant="success" icon="check-circle">
                Payment initiated: {{ session('payment_success') }}
            </flux:callout>
        @endif

        @if($errorMessage)
            <flux:callout variant="danger" icon="exclamation-triangle">
                {{ $errorMessage }}
            </flux:callout>
        @endif

        @if($isRedirecting)
            <flux:callout variant="success" icon="arrow-path">
                Redirecting to payment gateway, please wait...
            </flux:callout>
        @endif
    </flux:card>
</div>