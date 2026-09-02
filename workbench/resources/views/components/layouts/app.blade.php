<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Payment Gateway Bench' }}</title>
    <link rel="stylesheet" href="/workbench/flux.css">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    @fluxAppearance
</head>
<body class="min-h-full bg-zinc-50 text-zinc-900 dark:bg-zinc-900 dark:text-zinc-100">
    <div class="mx-auto max-w-5xl p-6">
        <nav class="mb-6 flex flex-wrap gap-4 text-sm">
            <a href="/" class="underline">Bench</a>
            <a href="/payment/gateways" class="underline">Gateways</a>
            <a href="/payment/transactions" class="underline">Transactions</a>
            <a href="/payment/coupons" class="underline">Coupons</a>
        </nav>

        {{ $slot }}
    </div>
    @fluxScripts
</body>
</html>
