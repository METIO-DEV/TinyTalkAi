<!-- Composant pour l'interface de chat -->
<div class="flex flex-col h-full bg-custom-white dark:bg-custom-light-dark-mode dark:text-custom-white rounded-lg shadow-xl">
    <!-- Zone des messages -->
    @livewire('chat-messages')
    
    <!-- Zone de saisie du message -->
    <div class="p-4 bg-transparent">
        @livewire('chat-form')
        
        <!-- Compteur de tokens -->
        <div class="mt-2">
            <livewire:token-counter />
        </div>
    </div>
</div>

<!-- Templates pour les messages -->
<template id="user-message-template">
    <div class="flex justify-end mb-4">
        <div class="bg-custom-black text-white dark:text-custom-white dark:bg-custom-white-dark-mode rounded-tl-xl rounded-tr-xl rounded-bl-xl rounded-br-sm py-2 px-4 max-w-[80%]">
            <div class="whitespace-pre-wrap message-content"></div>
        </div>
    </div>
</template>

<template id="ai-message-template">
    <div class="flex justify-start mb-4 animate-fade-in">
        <div class="bg-custom-light text-custom-black rounded-lg py-2 px-4 max-w-[80%] rounded-tl-xl rounded-tr-xl rounded-bl-sm rounded-br-xl">
            <div class="whitespace-pre-wrap message-content typing-animation"></div>
        </div>
    </div>
</template>

<template id="loading-message-template">
    <div class="flex justify-start mb-4 loading-message">
        <div class="bg-custom-light text-custom-black dark:text-custom-white dark:bg-custom-light-dark-mode rounded-lg py-3 px-4">
            <div class="flex items-center space-x-3">
                <div class="animate-spin h-5 w-5 text-custom-black dark:text-custom-white">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <div class="text-sm text-custom-black dark:text-custom-white ml-2 font-medium">Génération en cours...</div>
            </div>
        </div>
    </div>
</template>

<template id="error-message-template">
    <div class="flex justify-center mb-4">
        <div class="bg-red-100 border border-red-400 text-red-700 rounded-lg py-2 px-4 max-w-[80%]">
            <div class="whitespace-pre-wrap message-content"></div>
        </div>
    </div>
</template>

<template id="model-selection-message-template">
    <div id="model-selection-message" class=" p-4 my-4 text-center">
        <p class="text-custom-black dark:text-custom-white font-bold text-lg mt-1 model-name"></p>
    </div>
</template>

<style>
    .typing-animation {
        overflow: hidden;
        white-space: pre-wrap;
        margin: 0;
        letter-spacing: normal;
    }
    
    .typing-animation.active {
        /* Suppression du border-right qui créait le curseur */
    }
    
    .typing-animation.animate {
        /* Suppression de l'animation du curseur */
    }
    
    @keyframes typing {
        from { width: 0 }
        to { width: 100% }
    }
    
    @keyframes blink-caret {
        from, to { border-color: transparent }
        50% { border-color: #000; }
    }
</style>

<script>

// Auto‑scroll « toujours en bas » même après changement de conversation
// --------------------------------------------------------------------

(() => {
    let observer;

    const scrollToBottom = (container) => {
        if (container) container.scrollTop = container.scrollHeight;
    };

    const initScroll = () => {
        const container = document.getElementById('chat-messages');
        if (!container) return;

        // 1️⃣  Systématiquement en bas
        scrollToBottom(container);

        // 2️⃣  (Re)‑observe les ajouts
        observer?.disconnect();
        observer = new MutationObserver(() => scrollToBottom(container));
        observer.observe(container, { childList: true });
    };

    // Au chargement
    document.addEventListener('DOMContentLoaded', initScroll);

    // Après chaque diff Livewire
    document.addEventListener('livewire:update', initScroll);
})();
</script>
