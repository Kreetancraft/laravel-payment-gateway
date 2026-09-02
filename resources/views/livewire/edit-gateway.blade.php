<div class="space-y-6">
    <x-payment-gateway::page-header
        :title="__('Configure :gateway', ['gateway' => $label])"
        :subtitle="__('Update API keys, RSA certificates, environment mode, and currency settings.')"
    >
        <x-slot:breadcrumbs>
            <flux:breadcrumbs>
                <flux:breadcrumbs.item href="{{ \Kreetancraft\PaymentGateway\Layout::home() }}" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item href="{{ route(config('payment-gateway.routes.names.gateways', 'admin.payment.gateways')) }}" wire:navigate>{{ __('Gateways') }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>{{ $label }}</flux:breadcrumbs.item>
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
                    :title="__('Provider Details')"
                    :subtitle="__('Display properties and supported currencies.')"
                >
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label badge="{{ __('Required') }}">{{ __('Display Label') }}</flux:label>
                            <flux:input wire:model.blur="label" placeholder="{{ __('e.g. Stripe, Himalayan Bank') }}" required />
                            <flux:error name="label" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Icon URL') }}</flux:label>
                            <flux:input wire:model.blur="icon" placeholder="https://..." />
                            <flux:error name="icon" />
                        </flux:field>
                    </div>

                    <flux:field>
                        <flux:label>{{ __('Supported Currencies (comma separated)') }}</flux:label>
                        <flux:input wire:model.blur="currenciesInput" placeholder="USD, EUR, NPR, INR" />
                        <flux:description>{{ __('Currencies accepted by this gateway for checkouts.') }}</flux:description>
                        <flux:error name="currenciesInput" />
                    </flux:field>
                </x-payment-gateway::form-section>

                <x-payment-gateway::form-section
                    :title="__('Encrypted Credentials')"
                    :subtitle="__('All secrets, tokens, and private keys are encrypted in your database using AES-256.')"
                >
                    @if (empty($configFields))
                        <flux:text class="text-sm text-zinc-500">{{ __('No configurable credential fields required for this provider.') }}</flux:text>
                    @else
                        <div class="space-y-4">
                            @foreach ($configFields as $field)
                                @php($fKey = is_array($field) ? ($field['key'] ?? '') : '')
                                @php($fType = is_array($field) ? ($field['type'] ?? 'text') : 'text')
                                @php($fLabel = is_array($field) ? ($field['label'] ?? $fKey) : $fKey)
                                @php($fDesc = is_array($field) ? ($field['description'] ?? '') : '')
                                @php($fOptions = is_array($field) ? ($field['options'] ?? []) : [])

                                {{--
                                    A boolean is a toggle, not a text box. This
                                    field used to fall through to a plain input,
                                    so "Enable 3D Secure" accepted the string "N"
                                    — which the reader could not parse and turned
                                    into "yes". Every HBL payment then asked for
                                    3DS whatever the admin chose. A switch cannot
                                    produce that value at all.
                                --}}
                                @if ($fType === 'checkbox')
                                    <flux:switch
                                        :wire:key="'field-'.$fKey"
                                        wire:model="fieldValues.{{ $fKey }}"
                                        :label="$fLabel"
                                        :description="filled($fDesc) ? $fDesc : null"
                                    />

                                    @continue
                                @endif

                                <flux:field :wire:key="'field-'.$fKey">
                                    <flux:label>{{ $fLabel }}</flux:label>

                                    @if ($fType === 'textarea')
                                        <flux:textarea
                                            wire:model="fieldValues.{{ $fKey }}"
                                            rows="5"
                                            class="font-mono text-xs"
                                            placeholder="-----BEGIN RSA PRIVATE KEY-----&#10;...&#10;-----END RSA PRIVATE KEY-----"
                                        />
                                    @elseif ($fType === 'select')
                                        <flux:select wire:model="fieldValues.{{ $fKey }}">
                                            @foreach ($fOptions as $optValue => $optLabel)
                                                <flux:select.option value="{{ $optValue }}">{{ $optLabel }}</flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    @elseif ($fType === 'password')
                                        <flux:input
                                            type="password"
                                            wire:model="fieldValues.{{ $fKey }}"
                                            placeholder="••••••••••••••••"
                                        />
                                    @else
                                        <flux:input
                                            type="text"
                                            wire:model="fieldValues.{{ $fKey }}"
                                        />
                                    @endif

                                    @if (filled($fDesc))
                                        <flux:description>{{ $fDesc }}</flux:description>
                                    @endif
                                    <flux:error name="fieldValues.{{ $fKey }}" />
                                </flux:field>
                            @endforeach
                        </div>
                    @endif
                </x-payment-gateway::form-section>
            </div>

            {{-- Sidebar Settings (1/3) --}}
            <div class="space-y-6">
                <x-payment-gateway::form-section :title="__('Status & Environment')">
                    <flux:field>
                        <flux:label>{{ __('Environment Mode') }}</flux:label>
                        <flux:select wire:model="environment">
                            <flux:select.option value="demo">{{ __('Demo / Sandbox') }}</flux:select.option>
                            <flux:select.option value="live">{{ __('Production / Live') }}</flux:select.option>
                        </flux:select>
                        <flux:error name="environment" />
                    </flux:field>

                    <flux:separator variant="subtle" />

                    <flux:switch
                        wire:model="enabled"
                        :label="__('Gateway Enabled')"
                        :description="__('Show this gateway as an option during customer checkout.')"
                    />

                    <flux:separator variant="subtle" />

                    <flux:switch
                        wire:model="checkout_redirect"
                        :label="__('Hosted Checkout Redirect')"
                        :description="__('Redirect customers to provider page vs embedded form.')"
                    />
                </x-payment-gateway::form-section>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 border-t border-zinc-200 pt-5 dark:border-zinc-800">
            <flux:button
                href="{{ route(config('payment-gateway.routes.names.gateways', 'admin.payment.gateways')) }}"
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
                <span wire:loading.remove wire:target="save">{{ __('Save Configuration') }}</span>
                <span wire:loading wire:target="save">{{ __('Saving…') }}</span>
            </flux:button>
        </div>
    </form>
</div>
