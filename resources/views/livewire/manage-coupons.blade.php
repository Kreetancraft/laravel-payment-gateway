<div class="p-6 space-y-6">
    @if(session()->has('coupon_message'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('coupon_message') }}
        </flux:callout>
    @endif

    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="lg">Discount Coupons</flux:heading>
            <flux:text variant="muted">Manage percentage, fixed amount, tiered, and buy-X-get-Y discount coupons.</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="create">Create Coupon</flux:button>
    </div>

    <flux:card class="overflow-hidden p-0">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Code</flux:table.column>
                <flux:table.column>Type</flux:table.column>
                <flux:table.column>Value</flux:table.column>
                <flux:table.column>Usage</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column align="end">Actions</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($coupons as $coupon)
                    <flux:table.row wire:key="coupon-{{ $coupon->id }}">
                        <flux:table.cell>
                            <div>
                                <div class="font-mono font-medium">{{ $coupon->code }}</div>
                                <div class="text-xs text-zinc-500">{{ $coupon->name }}</div>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="zinc">{{ str_replace('_', ' ', ucfirst($coupon->type)) }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($coupon->type === 'percentage')
                                {{ $coupon->value }}%
                            @elseif($coupon->type === 'fixed')
                                ${{ number_format($coupon->value / 100, 2) }}
                            @elseif($coupon->type === 'free_shipping')
                                Free Shipping
                            @else
                                {{ $coupon->value }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $coupon->usage_count }} / {{ $coupon->usage_limit ?? '∞' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($coupon->is_active && (! $coupon->expires_at || $coupon->expires_at->isFuture()))
                                <flux:badge color="green" size="sm">Active</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">Inactive</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex items-center justify-end gap-2">
                                <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $coupon->id }})">Edit</flux:button>
                                <flux:button size="sm" variant="danger" icon="trash" wire:click="delete({{ $coupon->id }})" wire:confirm="Are you sure you want to delete this coupon?">Delete</flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center py-8 text-zinc-500">
                            No coupons created yet. Click "Create Coupon" to add one.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <div class="p-4 border-t border-zinc-200 dark:border-zinc-800">
            {{ $coupons->links() }}
        </div>
    </flux:card>

    {{-- Create Modal --}}
    <flux:modal wire:model="showCreateModal" class="w-full max-w-2xl">
        <div class="space-y-6">
            <flux:heading size="lg">Create Coupon</flux:heading>
            <flux:text variant="muted">Fill in the details below to create a new coupon.</flux:text>

            <form wire:submit.prevent="save" class="space-y-6">
                <flux:field>
                    <flux:label>Code</flux:label>
                    <flux:input wire:model="code" placeholder="SAVE20" required />
                    <flux:error name="code" />
                    <flux:description>Unique code customers will enter (e.g., SAVE20)</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>Name</flux:label>
                    <flux:input wire:model="name" placeholder="20% Off Summer Sale" required />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label>Description</flux:label>
                    <flux:textarea wire:model="description" rows="3" placeholder="Optional description" />
                </flux:field>

                <flux:field>
                    <flux:label>Type</flux:label>
                    <flux:select wire:model.live="type">
                        <flux:select.option value="percentage">Percentage Discount</flux:select.option>
                        <flux:select.option value="fixed">Fixed Amount</flux:select.option>
                        <flux:select.option value="buy_x_get_y">Buy X Get Y</flux:select.option>
                        <flux:select.option value="tiered">Tiered</flux:select.option>
                        <flux:select.option value="free_shipping">Free Shipping</flux:select.option>
                    </flux:select>
                    <flux:error name="type" />
                </flux:field>

                <flux:field>
                    <flux:label>Value</flux:label>
                    <flux:input type="number" wire:model="value" min="0" required />
                    <flux:error name="value" />
                    <flux:description>
                        @if($type === 'percentage')
                            Percentage (e.g., 20 for 20%)
                        @elseif($type === 'fixed')
                            Amount in cents (e.g., 5000 for $50.00)
                        @elseif($type === 'buy_x_get_y')
                            Buy quantity (e.g., 2 for "buy 2")
                        @elseif($type === 'tiered')
                            See conditions
                        @endif
                    </flux:description>
                </flux:field>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Max Discount Amount (cents, optional)</flux:label>
                        <flux:input type="number" wire:model="maxDiscountAmount" min="0" placeholder="5000 for $50.00 max" />
                        <flux:description>Cap the maximum discount for percentage coupons</flux:description>
                    </flux:field>

                    <flux:field>
                        <flux:label>Min Order Amount (cents, optional)</flux:label>
                        <flux:input type="number" wire:model="minOrderAmount" min="0" placeholder="10000 for $100.00" />
                        <flux:description>Minimum order amount to use this coupon</flux:description>
                    </flux:field>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Usage Limit (optional)</flux:label>
                        <flux:input type="number" wire:model="usageLimit" min="1" placeholder="100" />
                        <flux:description>Total times this coupon can be used</flux:description>
                    </flux:field>

                    <flux:field>
                        <flux:label>Per User Limit (optional)</flux:label>
                        <flux:input type="number" wire:model="usageLimitPerUser" min="1" placeholder="1" />
                        <flux:description>Max uses per customer</flux:description>
                    </flux:field>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Starts At (optional)</flux:label>
                        <flux:input type="datetime-local" wire:model="startsAt" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Expires At (optional)</flux:label>
                        <flux:input type="datetime-local" wire:model="expiresAt" />
                    </flux:field>
                </div>

                <div class="flex items-center justify-between">
                    <flux:field variant="inline">
                        <flux:switch wire:model="isActive" />
                        <flux:label>Active</flux:label>
                    </flux:field>

                    <flux:field variant="inline">
                        <flux:switch wire:model="isStackable" />
                        <flux:label>Stackable</flux:label>
                    </flux:field>

                    <flux:field variant="inline">
                        <flux:switch wire:model="isFreeShipping" />
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
        <div class="space-y-6">
            <flux:heading size="lg">Edit Coupon</flux:heading>
            <flux:text variant="muted">Changes apply immediately.</flux:text>

            <form wire:submit.prevent="update" class="space-y-6">
                <flux:field>
                    <flux:label>Code</flux:label>
                    <flux:input wire:model="code" required />
                    <flux:error name="code" />
                </flux:field>

                <flux:field>
                    <flux:label>Name</flux:label>
                    <flux:input wire:model="name" required />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label>Description</flux:label>
                    <flux:textarea wire:model="description" rows="3" />
                </flux:field>

                <flux:field>
                    <flux:label>Type</flux:label>
                    <flux:select wire:model.live="type">
                        <flux:select.option value="percentage">Percentage Discount</flux:select.option>
                        <flux:select.option value="fixed">Fixed Amount</flux:select.option>
                        <flux:select.option value="buy_x_get_y">Buy X Get Y</flux:select.option>
                        <flux:select.option value="tiered">Tiered</flux:select.option>
                        <flux:select.option value="free_shipping">Free Shipping</flux:select.option>
                    </flux:select>
                    <flux:error name="type" />
                </flux:field>

                <flux:field>
                    <flux:label>Value</flux:label>
                    <flux:input type="number" wire:model="value" min="0" required />
                    <flux:error name="value" />
                </flux:field>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Max Discount Amount (cents, optional)</flux:label>
                        <flux:input type="number" wire:model="maxDiscountAmount" min="0" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Min Order Amount (cents, optional)</flux:label>
                        <flux:input type="number" wire:model="minOrderAmount" min="0" />
                    </flux:field>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Usage Limit (optional)</flux:label>
                        <flux:input type="number" wire:model="usageLimit" min="1" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Per User Limit (optional)</flux:label>
                        <flux:input type="number" wire:model="usageLimitPerUser" min="1" />
                    </flux:field>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Starts At (optional)</flux:label>
                        <flux:input type="datetime-local" wire:model="startsAt" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Expires At (optional)</flux:label>
                        <flux:input type="datetime-local" wire:model="expiresAt" />
                    </flux:field>
                </div>

                <div class="flex items-center justify-between">
                    <flux:field variant="inline">
                        <flux:switch wire:model="isActive" />
                        <flux:label>Active</flux:label>
                    </flux:field>

                    <flux:field variant="inline">
                        <flux:switch wire:model="isStackable" />
                        <flux:label>Stackable</flux:label>
                    </flux:field>

                    <flux:field variant="inline">
                        <flux:switch wire:model="isFreeShipping" />
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