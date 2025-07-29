<div class="model-selector">
    <div class="mb-4">
        <h2 class="text-lg font-semibold text-custom-black dark:text-custom-white mb-2">Modèles</h2>
        <div class="space-y-2">
            @foreach($availableModels as $model)
                <button
                    wire:click="selectModel('{{ $model['name'] }}')"
                    class="model-button w-full text-left px-3 py-2 rounded-md transition-colors duration-200 {{ $selectedModel === $model['name'] ? 'bg-custom-white dark:bg-custom-light-dark-mode border-custom-black dark:border-custom-white' : 'bg-custom-mid hover:bg-custom-white dark:bg-custom-dark-dark-mode dark:hover:bg-custom-light-dark-mode' }} border"
                >
                    <div class="text-sm font-medium text-custom-black dark:text-custom-white">
                        {{ explode(':', $model['name'])[0] }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        {{ number_format($model['size'] / (1024 * 1024 * 1024), 2) }} GB
                    </div>
                </button>
            @endforeach
        </div>
    </div>
</div>
