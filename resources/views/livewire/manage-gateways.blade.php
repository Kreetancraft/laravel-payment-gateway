<div class="p-6 space-y-6">
    @if(session()->has('gateway_message'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('gateway_message') }}
        </flux:callout>
    @endif

    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="lg">Payment Gateways</flux:heading>
            <flux:text variant="muted">Enable, disable and configure your payment gateways.</flux:text>
        </div>
        <flux:badge variant="pill" color="zinc">{{ count($gateways) }} gateways</flux:badge>
    </div>

    <flux:card class="overflow-hidden p-0">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Gateway</flux:table.column>
                <flux:table.column>Currencies</flux:table.column>
                <flux:table.column>Capabilities</flux:table.column>
                <flux:table.column>Redirect</flux:table.column>
                <flux:table.column>Enabled</flux:table.column>
                <flux:table.column align="end">Actions</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach($gateways as $gw)
                    <flux:table.row wire:key="gw-{{ $gw['code'] }}">
                        <flux:table.cell>
                            <div class="flex items-center gap-3">
                                @if(filled($gw['icon']))
                                    <img src="{{ $gw['icon'] }}" alt="{{ $gw['label'] }}" class="h-6 w-auto object-contain" />
                                @endif
                                <div>
                                    <div class="font-medium text-sm">{{ $gw['label'] }}</div>
                                    <div class="text-xs text-zinc-500">{{ $gw['code'] }}</div>
                                </div>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-wrap gap-1">
                                @foreach($gw['currencies'] as $cur)
                                    <flux:badge size="sm" color="zinc">{{ $cur }}</flux:badge>
                                @endforeach
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="text-xs text-zinc-600 dark:text-zinc-400">{{ implode(', ', $gw['capabilities']) ?: '—' }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$gw['checkout_redirect'] ? 'green' : 'zinc'">{{ $gw['checkout_redirect'] ? 'Yes' : 'No' }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:switch wire:click="toggleGatewayEnabled('{{ $gw['code'] }}')" :checked="$gw['enabled']" />
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="openEditGatewayModal('{{ $gw['code'] }}')">Edit</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model="showEditModal" class="md:w-[640px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Edit {{ $editingCode }}</flux:heading>
                <flux:text variant="muted">Changes apply to runtime config. Update config/payment-gateway.php to persist.</flux:text>
            </div>

            <flux:field>
                <flux:label>Label</flux:label>
                <flux:input wire:model="editingLabel" />
                <flux:error name="editingLabel" />
            </flux:field>

            <flux:field>
                <flux:label>Icon URL</flux:label>
                <flux:input wire:model="editingIcon" placeholder="https://..." />
                <flux:error name="editingIcon" />
            </flux:field>

            <flux:field>
                <flux:label>Currencies (comma separated)</flux:label>
                <flux:input wire:model="editingCurrencies" placeholder="USD, NPR, EUR" />
                <flux:error name="editingCurrencies" />
            </flux:field>

            <flux:field variant="inline">
                <flux:switch wire:model="editingEnabled" />
                <flux:label>Enabled</flux:label>
            </flux:field>

            @if(!empty($configFields))
                <flux:separator />
                <flux:heading>Gateway credentials</flux:heading>
                @foreach($configFields as $key => $field)
                    <flux:field wire:key="field-{{ $key }}">
                        <flux:label>{{ $field['label'] ?? $key }}</flux:label>
                        @if(($field['type'] ?? 'text') === 'password')
                            <flux:input type="password" wire:model="fieldValues.{{ $key }}" placeholder="{{ $field['description'] ?? '' }}" viewable />
                        @elseif(($field['type'] ?? 'text') === 'select')
                            <flux:select wire:model="fieldValues.{{ $key }}">
                                @foreach($field['options'] ?? [] as $optVal => $optLabel)
                                    <flux:select.option value="{{ $optVal }}">{{ $optLabel }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        @elseif(($field['type'] ?? 'text') === 'textarea')
                            <flux:textarea wire:model="fieldValues.{{ $key }}" rows="4" placeholder="{{ $field['description'] ?? '' }}" />
                        @else
                            <flux:input type="text" wire:model="fieldValues.{{ $key }}" placeholder="{{ $field['description'] ?? '' }}" />
                        @endif
                        @if(!empty($field['description']))
                            <flux:description>{{ $field['description'] }}</flux:description>
                        @endif
                    </flux:field>
                @endforeach
            @endif

            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="closeEditModal">Cancel</flux:button>
                <flux:button variant="primary" wire:click="saveGatewayCredentials">Save changes</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
