<div class="p-6 space-y-6">
    @if(session()->has('coupon_message'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('coupon_message') }}
        </flux:callout>
    @endif

    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="lg">Coupons</flux:heading>
            <flux:text variant="muted">Create and manage discount coupons for your customers.</flux:text>
        </div>
        <flux:button wire:click="create" variant="primary" icon="plus">Create Coupon</flux:button>
    </div>

    <flux:card class="overflow-hidden">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Code</flux:table.column>
                <flux:table.column>Name</flux:table.column>
                <flux:table.column>Type</flux:table.column>
                <flux:table.column>Value</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Usage</flux:table.column>
                <flux:table.column>Expires</flux:table.column>
                <flux:table.column>Actions</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach($coupons as $coupon)
                    <flux:table.row wire:key="coupon-{{ $coupon->id }}">
                        <flux:table.cell class="font-mono font-medium">{{ $coupon->code }}</flux:table.cell>
                        <flux:table.cell>{{ $coupon->name }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge variant="pill" color="zinc">{{ $coupon->type }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($coupon->type === 'percentage')
                                {{ $coupon->value }}%
                            @elseif($coupon->type === 'fixed')
                                {{ number_format($coupon->value / 100, 2) }}
                            @elseif($coupon->type === 'buy_x_get_y')
                                Buy {{ $coupon->conditions['buy'] ?? 'X' }} Get {{ $coupon->conditions['get'] ?? 'Y' }}
                            @elseif($coupon->type === 'tiered')
                                Tiered
                            @elseif($coupon->type === 'free_shipping')
                                Free Shipping
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($coupon->is_active)
                                <flux:badge color="green">Active</flux:badge>
                            @else
                                <flux:badge color="zinc">Inactive</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($coupon->usage_limit)
                                {{ $coupon->usage_count }} / {{ $coupon->usage_limit }}
                            @else
                                Unlimited
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($coupon->expires_at)
                                {{ $coupon->expires_at->format('M d, Y') }}
                            @else
                                Never
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $coupon->id }})" />
                            <flux:button size="sm" variant="danger" icon="trash" wire:click="delete({{ $coupon->id }})" wire:confirm="Delete this coupon?" />
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        {{ $coupons->links() }}
    </flux:card>

    {{-- Create Modal --}}
    <flux:modal wire:model="showCreateModal" class="w-full max-w-2xl">
        <div class="space-y-6">
            <flux:heading size="lg">Create Coupon</flux:heading>
            <flux:text variant="muted">Fill in the details below to create a new coupon.</flux:text>

            <form wire:submit.prevent="save" class="space-y-6">
                <flux:field>
                    <flux:label>Code</flux:label>
                    <flux:input wire:model="newCode" placeholder="SAVE20" required />
                    <flux:error name="newCode" />
                    <flux:description>Unique code customers will enter (e.g., SAVE20)</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>Name</flux:label>
                    <flux:input wire:model="newName" placeholder="20% Off Summer Sale" required />
                    <flux:error name="newName" />
                </flux:field>

                <flux:field>
                    <flux:label>Description</flux:label>
                    <flux:textarea wire:model="newDescription" rows="3" placeholder="Optional description" />
                </flux:field>

                <flux:field>
                    <flux:label>Type</flux:label>
                    <flux:select wire:model="newType">
                        <flux:select.option value="percentage">Percentage Discount</flux:select.option>
                        <flux:select.option value="fixed">Fixed Amount</flux:select.option>
                        <flux:select.option value="buy_x_get_y">Buy X Get Y</flux:select.option>
                        <flux:select.option value="tiered">Tiered</flux:select.option>
                        <flux:select.option value="free_shipping">Free Shipping</flux:select.option>
                    </flux:select>
                    <flux:error name="newType" />
                </flux:field>

                <flux:field>
                    <flux:label>Value</flux:label>
                    <flux:input type="number" wire:model="newValue" min="0" required />
                    <flux:error name="newValue" />
                    <flux:description>
                        @if($newType === 'percentage')
                            Percentage (e.g., 20 for 20%)
                        @elseif($newType === 'fixed')
                            Amount in cents (e.g., 5000 for $50.00)
                        @elseif($newType === 'buy_x_get_y')
                            Buy quantity (e.g., 2 for "buy 2")
                        @elseif($newType === 'tiered')
                            See conditions
                        @endif
                    </flux:description>
                </flux:field>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Max Discount Amount (cents, optional)</flux:label>
                        <flux:input type="number" wire:model="newMaxDiscountAmount" min="0" placeholder="5000 for $50.00 max" />
                        <flux:description>Cap the maximum discount for percentage coupons</flux:description>
                    </flux:field>

                    <flux:field>
                        <flux:label>Min Order Amount (cents, optional)</flux:label>
                        <flux:input type="number" wire:model="newMinOrderAmount" min="0" placeholder="10000 for $100.00" />
                        <flux:description>Minimum order amount to use this coupon</flux:description>
                    </flux:field>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Usage Limit (optional)</flux:label>
                        <flux:input type="number" wire:model="newUsageLimit" min="1" placeholder="100" />
                        <flux:description>Total times this coupon can be used</flux:description>
                    </flux:field>

                    <flux:field>
                        <flux:label>Per User Limit (optional)</flux:label>
                        <flux:input type="number" wire:model="newUsageLimitPerUser" min="1" placeholder="1" />
                        <flux:description>Max uses per customer</flux:description>
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>Allowed User IDs (comma separated, optional)</flux:label>
                    <flux:input wire:model="newUserIds" placeholder="1,2,3" />
                    <flux:description>Restrict coupon to specific users</flux:description>
                </flux:field>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Starts At (optional)</flux:label>
                        <flux:input type="datetime-local" wire:model="newStartsAt" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Expires At (optional)</flux:label>
                        <flux:input type="datetime-local" wire:model="newExpiresAt" />
                    </flux:field>
                </div>

                <flux:field variant="inline">
                    <flux:switch wire:model="newIsActive" />
                    <flux:label>Active</flux:label>
                </flux:field>

                <flux:field>
                    <flux:label>Max Discount Amount (cents, optional)</flux:label>
                    <flux:input type="number" wire:model="newMaxDiscountAmount" min="0" placeholder="5000 for $50.00 max" />
                    <flux:description>Cap the maximum discount for percentage coupons</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>Min Order Amount (cents, optional)</flux:label>
                    <flux:input type="number" wire:model="newMinOrderAmount" min="0" placeholder="10000 for $100.00" />
                </flux:field>

                <flux:field>
                    <flux:label>Conditions (JSON, optional)</flux:label>
                    <flux:textarea wire:model="newConditions" rows="4" placeholder='{"min_order_amount": 5000, "currencies": ["USD", "EUR"]}' />
                    <flux:description>Advanced conditions: min_order_amount, currencies, time_windows, etc.</flux:description>
                </flux:field>

                <div class="flex items-center justify-between">
                    <flux:field variant="inline">
                        <flux:switch wire:model="newIsStackable" />
                        <flux:label>Stackable</flux:label>
                    </flux:field>

                    <flux:field variant="inline">
                        <flux:switch wire:model="newIsFreeShipping" />
                        <flux:label>Free Shipping</flux:label>
                    </flux:field>
                </div>

                <div class="flex justify-end gap-3 pt-4">
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary" icon="check">Create Coupon</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- Edit Modal --}}
    <flux:modal wire:model="showEditModal" class="w-full max-w-2xl">
        <form wire:submit.prevent="update" class="space-y-6">
            <flux:heading size="lg">Edit Coupon</flux:heading>
            <flux:text variant="muted">Changes apply immediately.</flux:text>

            <form wire:submit.prevent="update" class="space-y-6">
                <flux:field>
                    <flux:label>Code</flux:label>
                    <flux:input wire:model="newCode" required />
                    <flux:error name="newCode" />
                </flux:field>

                <flux:field>
                    <flux:label>Name</flux:label>
                    <flux:input wire:model="newName" required />
                    <flux:error name="newName" />
                </flux:field>

                <flux:field>
                    <flux:label>Type</flux:label>
                    <flux:select wire:model="newType">
                        <flux:select.option value="percentage">Percentage Discount</flux:select.option>
                        <flux:select.option value="fixed">Fixed Amount</flux:select.option>
                        <flux:select.option value="buy_x_get_y">Buy X Get Y</flux:select.option>
                        <flux:select.option value="tiered">Tiered</flux:select.option>
                        <flux:select.option value="free_shipping">Free Shipping</flux:select.option>
                    </flux:select>
                    <flux:error name="newType" />
                </flux:field>

                <flux:field>
                    <flux:label>Value</flux:label>
                    <flux:input type="number" wire:model="newValue" min="0" required />
                    <flux:error name="newValue" />
                </flux:field>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Max Discount Amount (cents, optional)</flux:label>
                        <flux:input type="number" wire:model="newMaxDiscountAmount" min="0" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Min Order Amount (cents, optional)</flux:label>
                        <flux:input type="number" wire:model="newMinOrderAmount" min="0" />
                    </flux:field>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Usage Limit (optional)</flux:label>
                        <flux:input type="number" wire:model="newUsageLimit" min="1" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Per User Limit (optional)</flux:label>
                        <flux:input type="number" wire:model="newUsageLimitPerUser" min="1" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>Allowed User IDs (comma separated, optional)</flux:label>
                    <flux:input wire:model="newUserIds" placeholder="1,2,3" />
                </flux:field>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Starts At (optional)</flux:label>
                        <flux:input type="datetime-local" wire:model="newStartsAt" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Expires At (optional)</flux:label>
                        <flux:input type="datetime-local" wire:model="newExpiresAt" />
                    </flux:field>
                </div>

                <flux:field variant="inline">
                    <flux:switch wire:model="newIsActive" />
                    <flux:label>Active</flux:label>
                </flux:field>

                <flux:field>
                    <flux:label>Max Discount Amount (cents, optional)</flux:label>
                    <flux:input type="number" wire:model="newMaxDiscountAmount" min="0" />
                </flux:field>

                <flux:field>
                    <flux:label>Min Order Amount (cents, optional)</flux:label>
                    <flux:input type="number" wire:model="newMinOrderAmount" min="0" />
                </flux:field>

                <flux:field>
                    <flux:label>Conditions (JSON, optional)</flux:label>
                    <flux:textarea wire:model="newConditions" rows="4" placeholder='{"min_order_amount": 5000, "currencies": ["USD", "EUR"]}' />
                </flux:field>

                <div class="flex items-center justify-between">
                    <flux:field variant="inline">
                        <flux:switch wire:model="newIsStackable" />
                        <flux:label>Stackable</flux:label>
                    </flux:field>

                    <flux:field variant="inline">
                        <flux:switch wire:model="newIsFreeShipping" />
                        <flux:label>Free Shipping</flux:label>
                    </flux:field>
                </div>

                <div class="flex justify-end gap-3 pt-4">
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary" icon="check">Update Coupon</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>