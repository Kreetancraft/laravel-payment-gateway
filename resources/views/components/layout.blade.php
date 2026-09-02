@props([
    'title' => null,
])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @if(function_exists('fonts'))
            @fonts
        @endif

        {{--
            Only when the host has actually built them. This is a package view
            and those entry points belong to the application, so a host that
            names its assets differently — or has simply not run a build yet —
            used to get a ViteManifestNotFoundException. That is a 500 on
            /payment/success: the buyer has paid, and the page telling them so is
            the page that crashes.
        --}}
        @if (file_exists(public_path('hot')) || file_exists(public_path('build/manifest.json')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif

        {{--
            For hosts that do not build these entry points with Vite. Name a view
            in `payment-gateway.assets_view` and it is included here; leave it
            null and nothing renders. A missing view renders nothing rather than
            erroring, which is the same bargain the other packages make.
        --}}
        @includeIf(config('payment-gateway.assets_view'))
        @fluxAppearance
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-900 dark:bg-zinc-900 dark:text-zinc-100 flex flex-col antialiased">
        <main class="flex-1 flex items-center justify-center p-4 sm:p-6 lg:p-8">
            <div class="w-full max-w-2xl mx-auto">
                {{ $slot }}
            </div>
        </main>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
