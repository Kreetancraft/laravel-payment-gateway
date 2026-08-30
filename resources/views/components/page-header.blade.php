@props([
    'title',
    'subtitle' => null,
])

<div class="space-y-4">
    @if (isset($breadcrumbs))
        {{ $breadcrumbs }}
    @endif

    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="space-y-1">
            <flux:heading size="xl" level="1">{{ $title }}</flux:heading>
            @if ($subtitle)
                <flux:subheading class="max-w-2xl">{{ $subtitle }}</flux:subheading>
            @endif

            @if (isset($badges))
                <div class="flex flex-wrap items-center gap-2 pt-1">
                    {{ $badges }}
                </div>
            @endif
        </div>

        @if (isset($actions) || $slot->isNotEmpty())
            <div class="flex flex-wrap items-center gap-2">
                {{ $actions ?? $slot }}
            </div>
        @endif
    </div>
</div>
