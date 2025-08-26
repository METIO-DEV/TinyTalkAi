<div
    class="model-selector w-full"
>
    @if(count($availableModels) > 0)
        <select
            wire:model="selectedModel"
            wire:change="selectModel($event.target.value)"
            class="w-full px-3 py-2 rounded-md bg-custom-mid dark:bg-custom-light-dark-mode
                   text-custom-black dark:text-custom-white focus:outline-none"
        >
            @foreach ($availableModels as $model)
                <option
                    value="{{ $model['name'] }}"
                >
                    {{ explode(':', $model['name'])[0] }}
                    — {{ number_format($model['size'] / (1024 * 1024 * 1024), 2) }} GB
                </option>
            @endforeach
        </select>
    @else
        <div class="w-full px-3 py-2 rounded-md bg-custom-mid dark:bg-custom-light-dark-mode
                  text-custom-black dark:text-custom-white text-center">
            {{ __('No models available') }}
        </div>
    @endif
</div>
