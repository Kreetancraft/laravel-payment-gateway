@if ($errors->any())
    <flux:callout variant="danger" icon="exclamation-triangle" class="space-y-1">
        <div class="font-medium text-sm">{{ __('Please correct the errors below:') }}</div>
        <ul class="list-disc list-inside text-xs space-y-0.5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </flux:callout>
@endif
