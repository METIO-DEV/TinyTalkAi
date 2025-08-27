<div class="token-counter xl:w-[80%] lg:w-[60%] w-full">
    @if($selectedModel)
        <div class="flex flex-col">
            <div class="flex flex-row justify-center xl:gap-8 lg:gap-4 gap-2 items-center text-xs text-custom-black dark:text-custom-white">
                <span class="hidden lg:inline">{{ $tokensUsed }} / {{ $tokenLimit ?? '?' }} tokens</span>
                <div class="relative xl:w-[60%] lg:w-[50%] w-full bg-custom-mid rounded-full h-4 dark:bg-gray-700 overflow-hidden">
                    <div class="h-4 rounded-full transition-all duration-300 ease-in-out" 
                         style="width: {{ $this->getTokenPercentageProperty() }}%; background-color: {{ $this->getProgressColorProperty() }}"></div>
                    <!-- Mobile: show token info inside the bar -->
                    <span class="absolute inset-0 flex items-center justify-center text-[10px] text-custom-black dark:text-custom-white lg:hidden">
                        {{ $tokensUsed }} / {{ $tokenLimit ?? '?' }}
                    </span>
                    <!-- Desktop: show percentage on the right inside the bar -->
                    <span class="absolute right-2 top-1/2 -translate-y-1/2 text-[10px] text-custom-black dark:text-custom-white hidden lg:inline">
                        {{ $this->getTokenPercentageProperty() }}%
                    </span>
                </div>
                <button 
                    wire:click="summarizeConversation" 
                    wire:loading.attr="disabled"
                    wire:target="summarizeConversation"
                    wire:loading.class="opacity-50 cursor-not-allowed"
                    {{ $isMessageSending ? 'disabled' : '' }}
                    class="text-xs px-3 py-1 rounded-md transition-colors duration-200 text-custom-black bg-custom-mid hover:border-custom-black hover:dark:border-custom-black border disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap min-w-[80px]"
                >
                    <span wire:loading.remove wire:target="summarizeConversation" class="flex items-center justify-center">
                        {{ __('Résumer') }}
                    </span>
                    <span wire:loading wire:target="summarizeConversation" class="inline-flex items-center">
                        <svg class="inline-block animate-spin mr-1 h-3 w-3 align-middle text-custom-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="align-middle">{{ __('Résumé...') }}</span>
                    </span>
                </button>
            </div>
        </div>
    @else
        <div class="text-xs text-gray-500 dark:text-gray-400">
            {{ __('Sélectionnez un modèle pour afficher la limite de tokens') }}
        </div>
    @endif
</div>
