<x-layouts.app>
    <div class="max-w-xl mx-auto py-12 text-center space-y-4">
        <flux:heading size="xl">Payment Successful</flux:heading>
        @if(isset($result) && $result->success)
            <flux:callout variant="success">Payment verified: {{ $result->transactionId }} — {{ $result->status }}</flux:callout>
        @else
            <flux:callout variant="info">Thank you for your payment.</flux:callout>
            @if(isset($result) && !$result->success)
                <flux:callout variant="warning">{{ $result->errorMessage }}</flux:callout>
            @endif
        @endif
        <flux:button :href="route(config('payment-gateway.routes.names.checkout', 'payment.checkout'))" variant="primary">Back to checkout</flux:button>
    </div>
</x-layouts.app>
