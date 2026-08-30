@props([
    'icon' => 'inbox',
    'title' => 'No items found',
    'description' => null,
])

<div class="flex flex-col items-center justify-center p-12 text-center border border-dashed border-zinc-200 dark:border-zinc-800 rounded-xl bg-zinc-50/50 dark:bg-zinc-900/30">
    <div class="p-3 bg-zinc-100 dark:bg-zinc-800 rounded-full mb-3">
        <flux:icon :icon="$icon" class="size-8 text-zinc-500" />
    </div>
    <flux:heading size="lg">{{ $title }}</flux:heading>
    @if ($description)
        <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400 max-w-sm">{{ $description }}</flux:text>
    @endif

    @if ($slot->isNotEmpty())
        <div class="mt-4 flex items-center gap-2">
            {{ $slot }}
        </div>
    @endif
</div>
