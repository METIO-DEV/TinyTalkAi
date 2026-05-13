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
            </div>
        </div>
    @else
        <div class="text-xs text-gray-500 dark:text-gray-400">
            {{ __('Sélectionnez un modèle pour afficher la limite de tokens') }}
        </div>
    @endif
</div>
