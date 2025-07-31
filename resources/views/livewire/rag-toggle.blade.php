<div class="flex items-center justify-between ">
    <div class="flex flex-col">
        <span class="text-sm font-medium">Mode RAG</span>
        <span class="text-xs text-gray-500">Enrichit les réponses avec le contexte des documents</span>
    </div>
    <button 
        wire:click="toggle"
        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2"
        :class="{ 'bg-black': @js($enabled), 'bg-gray-300': !@js($enabled) }"
        x-data
    >
        <span
            class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
            :class="{ 'translate-x-6': @js($enabled), 'translate-x-1': !@js($enabled) }"
        ></span>
    </button>
</div>  
