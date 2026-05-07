<div
    class="model-selector w-full"
>
    @if(count($availableModels) > 0)
        <x-ui.select
            wire:model="selectedModel"
            wire:change="selectModel($event.target.value)"
        >
            @foreach ($availableModels as $model)
                <option
                    value="{{ $model['name'] }}"
                >
                    {{ explode(':', $model['name'])[0] }}
                    — {{ number_format($model['size'] / (1024 * 1024 * 1024), 2) }} GB
                </option>
            @endforeach
        </x-ui.select>
    @else
        <div class="w-full rounded-md border border-border bg-muted px-3 py-2 text-center text-sm text-muted-foreground">
            {{ __('No models available') }}
        </div>
    @endif
</div>
