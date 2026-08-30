@props([
    'rows' => 5,
    'columns' => 5,
])

<div class="animate-pulse space-y-4 p-4">
    <div class="h-6 bg-zinc-200 dark:bg-zinc-800 rounded w-1/4"></div>
    <div class="space-y-3">
        @for ($i = 0; $i < $rows; $i++)
            <div class="grid grid-cols-{{ $columns }} gap-4">
                @for ($j = 0; $j < $columns; $j++)
                    <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded"></div>
                @endfor
            </div>
        @endfor
    </div>
</div>
