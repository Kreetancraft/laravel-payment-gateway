@props([
    'title' => null,
    'subtitle' => null,
])

<flux:card class="space-y-4">
    @if ($title || $subtitle)
        <div class="space-y-1">
            @if ($title)
                <flux:heading size="lg">{{ $title }}</flux:heading>
            @endif
            @if ($subtitle)
                <flux:subheading class="text-xs text-zinc-500">{{ $subtitle }}</flux:subheading>
            @endif
        </div>
        <flux:separator variant="subtle" />
    @endif

    <div class="space-y-4">
        {{ $slot }}
    </div>
</flux:card>
