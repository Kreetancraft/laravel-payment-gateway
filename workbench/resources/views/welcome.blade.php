<x-layouts.app title="Payment Gateway Bench">
    <h1 class="text-2xl font-semibold">Payment Gateway Bench</h1>

    <p class="mt-2 max-w-2xl text-sm text-zinc-600 dark:text-zinc-400">
        A real Laravel app running this package, so a payment can be taken end to end against the
        gateway's own sandbox. Nothing here is committed with credentials — you paste those in once.
    </p>

    <ol class="mt-6 max-w-2xl list-decimal space-y-2 pl-5 text-sm">
        <li>
            Open <a href="/payment/gateways" class="underline">Gateways</a>, edit
            <strong>Himalayan Bank</strong>, paste the sandbox office id, API key, encryption key id
            and the four RSA keys, and enable it.
        </li>
        <li>
            The vendor's published sandbox values live in the demo app at
            <code class="rounded bg-zinc-200 px-1 dark:bg-zinc-800">himalayan-bank-payment-gateway/app/Services/HBL/SecurityData.php</code>.
        </li>
        <li>Run <code class="rounded bg-zinc-200 px-1 dark:bg-zinc-800">php artisan payment-gateway:status</code> — it should exit clean.</li>
        <li>Pay the invoice below. You will be redirected to PACO's hosted page.</li>
        <li>Come back and check <a href="/payment/transactions" class="underline">Transactions</a>.</li>
    </ol>

    <p class="mt-4 max-w-2xl rounded border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
        The sandbox only approves <strong>NPR</strong> test cards. A USD authorisation comes back
        “05 Do Not Honor”, which is the sandbox refusing, not a bug here.
    </p>

    @if ($invoice)
        <div class="mt-8 rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800">
            <h2 class="font-medium">{{ $invoice->number }}</h2>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                Outstanding:
                <strong>{{ number_format($invoice->paymentAmountCents() / 100, 2) }} {{ $invoice->currency }}</strong>
            </p>

            <a href="/payment/checkout?payableType=invoice&payableId={{ $invoice->id }}"
               class="mt-4 inline-block rounded bg-zinc-900 px-4 py-2 text-sm text-white dark:bg-white dark:text-zinc-900">
                Pay this invoice
            </a>

            <p class="mt-3 text-xs text-zinc-500">
                The amount is not in that link. Add <code>&amp;amount=1</code> and it is ignored —
                the price comes from the invoice.
            </p>
        </div>
    @endif

    <h2 class="mt-10 font-medium">Recent payments</h2>
    @if ($payments->isEmpty())
        <p class="mt-2 text-sm text-zinc-500">None yet.</p>
    @else
        <table class="mt-2 w-full text-left text-sm">
            <thead class="text-zinc-500">
                <tr><th class="py-1">Reference</th><th>Gateway</th><th>Amount</th><th>Status</th></tr>
            </thead>
            <tbody>
                @foreach ($payments as $payment)
                    <tr class="border-t border-zinc-200 dark:border-zinc-700">
                        <td class="py-1 font-mono text-xs">{{ $payment->gateway_reference ?? '—' }}</td>
                        <td>{{ $payment->gateway }}</td>
                        <td>{{ number_format($payment->amount_cents / 100, 2) }} {{ $payment->currency }}</td>
                        <td>{{ $payment->status->value }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</x-layouts.app>
