<x-payment-gateway::layout :title="__('Checkout')">
    <livewire:payment.checkout :gateway="$gateway ?? null" />
</x-payment-gateway::layout>
