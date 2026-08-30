@props([
    'title' => null,
    'subtitle' => null,
])

<flux:card {{ $attributes->merge(['class' => 'space-y-6']) }}>
    @if ($title)
        <div class="space-y-1">
            <flux:heading size="lg">{{ $title }}</flux:heading>
            @if ($subtitle)
                <flux:text size="sm" variant="subtle">{{ $subtitle }}</flux:text>
            @endif
        </div>
    @endif

    <div class="space-y-5">
        {{ $slot }}
    </div>
</flux:card>
