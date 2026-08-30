<div class="max-w-2xl mx-auto space-y-6">
    <flux:card class="space-y-6">
        {{-- ==================== STEP PROGRESS HEADER ==================== --}}
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="xl">{{ __('Checkout') }}</flux:heading>
                    <flux:text variant="subtle">
                        @if($step === 1)
                            {{ __('Step 1 of 3: Order Amount & Currency') }}
                        @elseif($step === 2)
                            {{ __('Step 2 of 3: Customer & Billing Details') }}
                        @else
                            {{ __('Step 3 of 3: Review, Coupons & Payment') }}
                        @endif
                    </flux:text>
                </div>
                <flux:badge size="sm" color="zinc" class="font-mono">Step {{ $step }}/3</flux:badge>
            </div>

            {{-- Step Tracker Bar --}}
            <div class="grid grid-cols-3 gap-2 pt-1">
                {{-- Step 1 Tab --}}
                <button
                    type="button"
                    wire:click="goToStep(1)"
                    class="flex items-center gap-2 p-2 rounded-lg border text-start transition {{ $step === 1 ? 'border-zinc-900 bg-zinc-50 dark:border-zinc-100 dark:bg-zinc-800' : ($step > 1 ? 'border-emerald-500/40 bg-emerald-50/30 dark:bg-emerald-950/10' : 'border-zinc-200 dark:border-zinc-800 opacity-60') }}"
                >
                    <div class="flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $step > 1 ? 'bg-emerald-500 text-white' : ($step === 1 ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'bg-zinc-200 dark:bg-zinc-700 text-zinc-600') }}">
                        @if($step > 1)
                            <flux:icon icon="check" class="size-3.5" />
                        @else
                            1
                        @endif
                    </div>
                    <div class="min-w-0 hidden sm:block">
                        <div class="text-xs font-medium truncate">{{ __('1. Order') }}</div>
                    </div>
                </button>

                {{-- Step 2 Tab --}}
                <button
                    type="button"
                    wire:click="goToStep(2)"
                    class="flex items-center gap-2 p-2 rounded-lg border text-start transition {{ $step === 2 ? 'border-zinc-900 bg-zinc-50 dark:border-zinc-100 dark:bg-zinc-800' : ($step > 2 ? 'border-emerald-500/40 bg-emerald-50/30 dark:bg-emerald-950/10' : 'border-zinc-200 dark:border-zinc-800 opacity-60') }}"
                >
                    <div class="flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $step > 2 ? 'bg-emerald-500 text-white' : ($step === 2 ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'bg-zinc-200 dark:bg-zinc-700 text-zinc-600') }}">
                        @if($step > 2)
                            <flux:icon icon="check" class="size-3.5" />
                        @else
                            2
                        @endif
                    </div>
                    <div class="min-w-0 hidden sm:block">
                        <div class="text-xs font-medium truncate">{{ __('2. Customer') }}</div>
                    </div>
                </button>

                {{-- Step 3 Tab --}}
                <button
                    type="button"
                    wire:click="goToStep(3)"
                    class="flex items-center gap-2 p-2 rounded-lg border text-start transition {{ $step === 3 ? 'border-zinc-900 bg-zinc-50 dark:border-zinc-100 dark:bg-zinc-800' : 'border-zinc-200 dark:border-zinc-800 opacity-60' }}"
                >
                    <div class="flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $step === 3 ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'bg-zinc-200 dark:bg-zinc-700 text-zinc-600') }}">
                        3
                    </div>
                    <div class="min-w-0 hidden sm:block">
                        <div class="text-xs font-medium truncate">{{ __('3. Payment') }}</div>
                    </div>
                </button>
            </div>
        </div>

        <flux:separator variant="subtle" />

        {{-- Global Error / Redirect Alerts --}}
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

        {{-- ==================== STEP 1: ORDER & AMOUNT ==================== --}}
        @if($step === 1)
            <div class="space-y-5">
                <flux:field>
                    <flux:label>{{ __('Order / Item Description') }}</flux:label>
                    <flux:input
                        type="text"
                        wire:model.live.debounce.300ms="orderTitle"
                        placeholder="{{ __('e.g. Annapurna Sanctuary Trek Pass') }}"
                    />
                </flux:field>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label badge="{{ __('Required') }}">{{ __('Amount') }}</flux:label>
                        <flux:input
                            type="text"
                            wire:model.live.debounce.300ms="amountInput"
                            placeholder="100.00"
                            autocomplete="off"
                        />
                        <flux:error name="amountInput" />
                    </flux:field>

                    <flux:field>
                        <flux:label badge="{{ __('Required') }}">{{ __('Currency') }}</flux:label>
                        <flux:select wire:model.live="currencyInput">
                            <flux:select.option value="USD">USD - US Dollar</flux:select.option>
                            <flux:select.option value="NPR">NPR - Nepalese Rupee (Himalayan Bank)</flux:select.option>
                            <flux:select.option value="EUR">EUR - Euro</flux:select.option>
                            <flux:select.option value="GBP">GBP - British Pound</flux:select.option>
                            <flux:select.option value="THB">THB - Thai Baht</flux:select.option>
                            <flux:select.option value="INR">INR - Indian Rupee</flux:select.option>
                            <flux:select.option value="AUD">AUD - Australian Dollar</flux:select.option>
                            <flux:select.option value="CAD">CAD - Canadian Dollar</flux:select.option>
                        </flux:select>
                        <flux:error name="currencyInput" />
                    </flux:field>
                </div>

                <div class="flex items-center justify-end pt-4 border-t border-zinc-200 dark:border-zinc-800">
                    <flux:button
                        type="button"
                        wire:click="nextStep"
                        variant="primary"
                        icon-trailing="arrow-right"
                    >
                        {{ __('Continue to Customer Info') }}
                    </flux:button>
                </div>
            </div>
        @endif

        {{-- ==================== STEP 2: CUSTOMER DETAILS ==================== --}}
        @if($step === 2)
            <div class="space-y-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label badge="{{ __('Required') }}">{{ __('Customer Email') }}</flux:label>
                        <flux:input
                            type="email"
                            wire:model.blur="customerEmail"
                            placeholder="customer@example.com"
                            required
                        />
                        <flux:error name="customerEmail" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Customer Name') }}</flux:label>
                        <flux:input
                            type="text"
                            wire:model.blur="customerName"
                            placeholder="John Doe"
                        />
                        <flux:error name="customerName" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>{{ __('Phone Number (optional)') }}</flux:label>
                    <flux:input
                        type="text"
                        wire:model.blur="customerPhone"
                        placeholder="+977-9801234567"
                    />
                </flux:field>

                <div class="flex items-center justify-between pt-4 border-t border-zinc-200 dark:border-zinc-800">
                    <flux:button
                        type="button"
                        wire:click="previousStep"
                        variant="subtle"
                        icon="arrow-left"
                    >
                        {{ __('Back') }}
                    </flux:button>

                    <flux:button
                        type="button"
                        wire:click="nextStep"
                        variant="primary"
                        icon-trailing="arrow-right"
                    >
                        {{ __('Continue to Payment') }}
                    </flux:button>
                </div>
            </div>
        @endif

        {{-- ==================== STEP 3: COUPONS, GATEWAYS & REVIEW ==================== --}}
        @if($step === 3)
            <div class="space-y-6">
                {{-- 1. Integrated Coupon Section --}}
                <div class="space-y-2">
                    <flux:label>{{ __('Discount Coupon') }}</flux:label>
                    @if($appliedCouponCode)
                        <div class="flex items-center justify-between p-3 rounded-lg bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-500/30">
                            <div class="flex items-center gap-2">
                                <flux:icon icon="ticket" class="size-5 text-emerald-600 dark:text-emerald-400" />
                                <div>
                                    <span class="font-mono font-bold text-sm text-emerald-700 dark:text-emerald-300">{{ $appliedCouponCode }}</span>
                                    <span class="text-xs text-emerald-600 dark:text-emerald-400 ml-1.5 font-medium">
                                        (-{{ $this->currencyInput }} {{ number_format($appliedDiscountCents / 100, 2) }} discount)
                                    </span>
                                    @if($hasFreeShipping)
                                        <flux:badge size="sm" color="emerald" class="ml-1.5">{{ __('Free Shipping') }}</flux:badge>
                                    @endif
                                </div>
                            </div>
                            <flux:button wire:click="removeCoupon" size="xs" variant="ghost" icon="x-mark">
                                {{ __('Remove') }}
                            </flux:button>
                        </div>
                    @else
                        <div class="flex gap-2">
                            <flux:input
                                type="text"
                                wire:model="couponCode"
                                wire:keydown.enter.prevent="applyCoupon"
                                placeholder="{{ __('Enter promo or coupon code (e.g. SAVE10)') }}"
                                class="font-mono uppercase"
                            />
                            <flux:button wire:click="applyCoupon" variant="subtle">
                                {{ __('Apply') }}
                            </flux:button>
                        </div>
                    @endif
                </div>

                {{-- 2. Gateway Selector --}}
                <div class="space-y-3">
                    <flux:label>{{ __('Choose Payment Method') }}</flux:label>
                    <div class="space-y-2.5">
                        @forelse($this->enabledGateways as $gw)
                            @php($isSupported = in_array($this->currencyInput, $gw['currencies'], true))
                            <button
                                type="button"
                                wire:key="gw-{{ $gw['code'] }}"
                                wire:click="$set('selectedGateway', '{{ $gw['code'] }}')"
                                class="w-full flex items-center gap-3.5 rounded-lg border p-3.5 text-start transition {{ $selectedGateway === $gw['code'] ? 'border-zinc-900 bg-zinc-50 dark:border-zinc-100 dark:bg-zinc-800 ring-1 ring-zinc-900 dark:ring-zinc-100' : 'border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }} {{ ! $isSupported ? 'opacity-60' : '' }}"
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
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        <span class="text-[11px] text-zinc-500">Currencies:</span>
                                        @foreach ($gw['currencies'] ?? [] as $cur)
                                            <span class="text-[11px] font-mono {{ $cur === $currencyInput ? 'font-bold text-emerald-600 dark:text-emerald-400' : 'text-zinc-500' }}">
                                                {{ $cur }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>

                                @if(!empty($gw['checkout_redirect']))
                                    <flux:badge size="sm" color="zinc">{{ __('Hosted Redirect') }}</flux:badge>
                                @endif
                            </button>
                        @empty
                            <div class="p-4 rounded-lg bg-amber-50 text-amber-800 dark:bg-amber-950/20 dark:text-amber-300 text-sm">
                                {{ __('No payment gateways are currently enabled in the database.') }}
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- 3. Order Summary Box --}}
                <div class="rounded-xl bg-zinc-50 dark:bg-zinc-800/60 p-4 border border-zinc-200 dark:border-zinc-700 space-y-3">
                    <div class="flex items-center justify-between text-sm font-medium">
                        <span>{{ $orderTitle ?: __('Order Subtotal') }}</span>
                        <span>{{ $this->currencyInput }} {{ number_format($this->getAmountInCents() / 100, 2) }}</span>
                    </div>

                    @if($appliedDiscountCents > 0)
                        <div class="flex items-center justify-between text-sm text-emerald-600 dark:text-emerald-400 font-medium">
                            <span>{{ __('Coupon (:code)', ['code' => $appliedCouponCode]) }}</span>
                            <span>-{{ $this->currencyInput }} {{ number_format($appliedDiscountCents / 100, 2) }}</span>
                        </div>
                    @endif

                    @if($hasFreeShipping)
                        <div class="flex items-center justify-between text-sm text-emerald-600 dark:text-emerald-400 font-medium">
                            <span>{{ __('Shipping') }}</span>
                            <span>{{ __('FREE') }}</span>
                        </div>
                    @endif

                    <div class="pt-2 border-t border-zinc-200 dark:border-zinc-700 flex items-baseline justify-between">
                        <span class="font-bold text-base">{{ __('Total Payable') }}</span>
                        <span class="font-bold text-xl text-zinc-900 dark:text-zinc-100">
                            {{ $this->currencyInput }} {{ number_format($this->finalAmountCents / 100, 2) }}
                        </span>
                    </div>
                </div>

                {{-- 4. Actions --}}
                <div class="flex items-center justify-between pt-2">
                    <flux:button
                        type="button"
                        wire:click="previousStep"
                        variant="subtle"
                        icon="arrow-left"
                    >
                        {{ __('Edit Details') }}
                    </flux:button>

                    <flux:button
                        type="button"
                        variant="primary"
                        wire:click="charge"
                        wire:loading.attr="disabled"
                        wire:target="charge"
                        class="px-6 font-semibold"
                    >
                        <span wire:loading.remove wire:target="charge">
                            {{ __('Pay :currency :amount', ['currency' => $this->currencyInput, 'amount' => number_format($this->finalAmountCents / 100, 2)]) }}
                        </span>
                        <span wire:loading wire:target="charge" class="flex items-center gap-2">
                            <flux:icon icon="arrow-path" class="animate-spin size-4" />
                            {{ __('Processing…') }}
                        </span>
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:card>
</div>