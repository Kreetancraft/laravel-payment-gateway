<x-layouts.app>
    <div class="max-w-xl mx-auto py-12 text-center space-y-4">
        <flux:heading size="xl">Payment Cancelled</flux:heading>
        <flux:callout variant="warning">Your payment was cancelled. No charges were made.</flux:callout>
        <flux:button :href="route(config('payment-gateway.routes.names.checkout', 'payment.checkout'))" variant="primary">Try again</flux:button>
    </div>
</x-layouts.app>
