@props([
    'icon' => 'inbox',
    'heading' => null,
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center gap-3 px-6 py-14 text-center']) }}>
    <div class="flex size-12 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
        <flux:icon :icon="$icon" class="size-6 text-zinc-400 dark:text-zinc-500" />
    </div>

    @if ($heading)
        <flux:heading size="lg">{{ $heading }}</flux:heading>
    @endif

    @if ($description)
        <flux:text class="max-w-sm text-zinc-500 dark:text-zinc-400">{{ $description }}</flux:text>
    @endif

    {{ $slot }}
</div>
