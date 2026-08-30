<div class="space-y-6">
    <x-payment-gateway::page-header
        :title="__('Create Coupon')"
        :subtitle="__('Define discount codes, percentages, fixed discounts, usage caps, and scheduling.')"
    >
        <x-slot:breadcrumbs>
            <flux:breadcrumbs>
                <flux:breadcrumbs.item href="{{ \Kreetancraft\PaymentGateway\Layout::home() }}" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item href="{{ route(config('payment-gateway.routes.names.coupons', 'admin.payment.coupons')) }}" wire:navigate>{{ __('Coupons') }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>{{ __('Create') }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>
        </x-slot:breadcrumbs>
    </x-payment-gateway::page-header>

    <flux:separator variant="subtle" />

    <x-payment-gateway::form-errors />

    <form wire:submit.prevent="save" class="space-y-6">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            {{-- Main Form (2/3) --}}
            <div class="space-y-6 lg:col-span-2">
                <x-payment-gateway::form-section
                    :title="__('Coupon Details')"
                    :subtitle="__('Basic identification and template presets.')"
                >
                    <flux:field>
                        <flux:label>{{ __('Start from a Template (optional)') }}</flux:label>
                        <flux:select wire:model.live="template" wire:change="applyTemplate($event.target.value)">
                            <flux:select.option value="">{{ __('Custom Coupon') }}</flux:select.option>
                            <flux:select.option value="percent_10">{{ __('10% Off Entire Order') }}</flux:select.option>
                            <flux:select.option value="percent_20">{{ __('20% Off Entire Order') }}</flux:select.option>
                            <flux:select.option value="fixed_20">{{ __('$20.00 Fixed Discount') }}</flux:select.option>
                            <flux:select.option value="free_shipping">{{ __('Free Shipping Promotion') }}</flux:select.option>
                        </flux:select>
                    </flux:field>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label badge="{{ __('Required') }}">{{ __('Coupon Code') }}</flux:label>
                            <flux:input wire:model.blur="code" placeholder="SAVE20" class="font-mono uppercase" required autofocus />
                            <flux:description>{{ __('Unique code entered by customers during checkout.') }}</flux:description>
                            <flux:error name="code" />
                        </flux:field>

                        <flux:field>
                            <flux:label badge="{{ __('Required') }}">{{ __('Display Name') }}</flux:label>
                            <flux:input wire:model.blur="name" placeholder="Summer 20% Off Sale" required />
                            <flux:error name="name" />
                        </flux:field>
                    </div>

                    <flux:field>
                        <flux:label>{{ __('Description (optional)') }}</flux:label>
                        <flux:textarea wire:model.blur="description" rows="2" placeholder="Internal campaign notes..." />
                        <flux:error name="description" />
                    </flux:field>
                </x-payment-gateway::form-section>

                <x-payment-gateway::form-section
                    :title="__('Discount Value & Rules')"
                    :subtitle="__('Configure how the discount amount is calculated.')"
                >
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label badge="{{ __('Required') }}">{{ __('Discount Type') }}</flux:label>
                            <flux:select wire:model.live="type">
                                <flux:select.option value="percentage">{{ __('Percentage Discount (%)') }}</flux:select.option>
                                <flux:select.option value="fixed">{{ __('Fixed Amount ($)') }}</flux:select.option>
                                <flux:select.option value="free_shipping">{{ __('Free Shipping') }}</flux:select.option>
                                <flux:select.option value="buy_x_get_y">{{ __('Buy X Get Y') }}</flux:select.option>
                            </flux:select>
                            <flux:error name="type" />
                        </flux:field>

                        <flux:field>
                            <flux:label badge="{{ __('Required') }}">
                                {{ $type === 'percentage' ? __('Discount Percentage (%)') : ($type === 'fixed' ? __('Amount in Cents ($10.00 = 1000)') : __('Value')) }}
                            </flux:label>
                            <flux:input type="number" wire:model.blur="value" min="0" required :disabled="$type === 'free_shipping'" />
                            <flux:error name="value" />
                        </flux:field>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>{{ __('Max Discount Cap (cents, optional)') }}</flux:label>
                            <flux:input type="number" wire:model.blur="maxDiscountAmount" min="0" placeholder="5000 for $50.00 max" />
                            <flux:description>{{ __('Caps the maximum discount for percentage discounts.') }}</flux:description>
                            <flux:error name="maxDiscountAmount" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Minimum Order Amount (cents, optional)') }}</flux:label>
                            <flux:input type="number" wire:model.blur="minOrderAmount" min="0" placeholder="10000 for $100.00 min" />
                            <flux:description>{{ __('Minimum checkout subtotal required.') }}</flux:description>
                            <flux:error name="minOrderAmount" />
                        </flux:field>
                    </div>
                </x-payment-gateway::form-section>
            </div>

            {{-- Sidebar Settings (1/3) --}}
            <div class="space-y-6">
                <x-payment-gateway::form-section :title="__('Usage Limits & Whitelist')">
                    <flux:field>
                        <flux:label>{{ __('Total Usage Limit (optional)') }}</flux:label>
                        <flux:input type="number" wire:model.blur="usageLimit" min="1" placeholder="{{ __('Unlimited') }}" />
                        <flux:description>{{ __('Total times this coupon can be redeemed across all customers.') }}</flux:description>
                        <flux:error name="usageLimit" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Per-Customer Limit (optional)') }}</flux:label>
                        <flux:input type="number" wire:model.blur="usageLimitPerUser" min="1" placeholder="1" />
                        <flux:description>{{ __('Max redemptions allowed per individual customer.') }}</flux:description>
                        <flux:error name="usageLimitPerUser" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Allowed User IDs (comma separated, optional)') }}</flux:label>
                        <flux:input wire:model.blur="userIdsInput" placeholder="1, 42, 108" />
                        <flux:description>{{ __('Restrict redemptions to specific customer account IDs.') }}</flux:description>
                        <flux:error name="userIdsInput" />
                    </flux:field>
                </x-payment-gateway::form-section>

                <x-payment-gateway::form-section :title="__('Schedule & Stacking')">
                    <flux:field>
                        <flux:label>{{ __('Starts At (optional)') }}</flux:label>
                        <flux:input icon="calendar" wire:model.blur="startsAt" placeholder="YYYY-MM-DD HH:MM" />
                        <flux:error name="startsAt" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Expires At (optional)') }}</flux:label>
                        <flux:input icon="calendar" wire:model.blur="expiresAt" placeholder="YYYY-MM-DD HH:MM" />
                        <flux:error name="expiresAt" />
                    </flux:field>

                    <flux:separator variant="subtle" />

                    <flux:switch
                        wire:model="isActive"
                        :label="__('Coupon Active')"
                        :description="__('Enable for customer checkout application.')"
                    />

                    <flux:separator variant="subtle" />

                    <flux:switch
                        wire:model="isStackable"
                        :label="__('Stackable with Other Coupons')"
                        :description="__('Allow combining with other promotional codes.')"
                    />
                </x-payment-gateway::form-section>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 border-t border-zinc-200 pt-5 dark:border-zinc-800">
            <flux:button
                href="{{ route(config('payment-gateway.routes.names.coupons', 'admin.payment.coupons')) }}"
                variant="ghost"
                wire:navigate
            >
                {{ __('Cancel') }}
            </flux:button>

            <flux:button
                type="submit"
                variant="primary"
                icon="check"
                wire:loading.attr="disabled"
                wire:target="save"
            >
                <span wire:loading.remove wire:target="save">{{ __('Create Coupon') }}</span>
                <span wire:loading wire:target="save">{{ __('Creating…') }}</span>
            </flux:button>
        </div>
    </form>
</div>
