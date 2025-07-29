<div class="token-counter w-[80%] mx-auto">
    @if($selectedModel)
        <div class="flex flex-col space-y-2">
            <div class="flex justify-between gap-2 items-center text-xs text-gray-600 dark:text-gray-300">
                <span>{{ $tokensUsed }} / {{ $tokenLimit ?? '?' }} tokens</span>
                <div class="w-[80%] bg-custom-mid rounded-full h-2 dark:bg-gray-700">
                    <div class="{{ $this->getProgressColorProperty() }} h-2 rounded-full transition-all duration-300 ease-in-out" style="width: {{ $this->getTokenPercentageProperty() }}%"></div>
                </div>
                <span>{{ $this->getTokenPercentageProperty() }}%</span>
                <button wire:click="summarizeConversation" class="text-xs px-2 py-1 rounded-md text-custom-black bg-custom-mid hover:border-custom-white border">Résumer</button>
            </div>
        </div>
    @else
        <div class="text-xs text-gray-500 dark:text-gray-400">
            Sélectionnez un modèle pour afficher la limite de tokens
        </div>
    @endif
</div>
