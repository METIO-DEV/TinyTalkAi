<!-- Composant pour l'interface de chat -->
<div class="relative flex flex-col h-full bg-card text-card-foreground border-border xl:rounded-lg xl:border xl:shadow-xl rounded-none shadow-none">
    <!-- Bouton flottant (mobile) pour ouvrir la sidebar -->
    <button
        id="open-sidebar-floating"
        type="button"
        class="xl:hidden absolute top-4 left-4 z-10 inline-flex size-9 items-center justify-center rounded-md border border-input bg-background text-foreground shadow-md transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        aria-label="{{ __('Menu') }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M3 5h14a1 1 0 100-2H3a1 1 0 000 2zm14 4H3a1 1 0 100 2h14a1 1 0 100-2zm0 6H3a1 1 0 100 2h14a1 1 0 100-2z"/></svg>
    </button>
    <!-- Zone des messages -->
    @livewire('chat-messages')
    
    <!-- Zone de saisie du message -->
    <div class="border-t border-border bg-card/95 p-3 sm:p-4">
        @livewire('chat-form')
        
        <!-- Compteur de tokens -->
        <div class="mt-3 flex flex-row justify-around items-center gap-3">
            <livewire:token-counter />
            <div class="lg:flex hidden">
                <livewire:rag-toggle />
            </div>
            
        </div>
    </div>
</div>

<!-- Templates pour les messages -->
<template id="user-message-template">
    <div class="flex justify-end mb-4">
        <div class="bg-primary text-primary-foreground rounded-tl-xl rounded-tr-xl rounded-bl-xl rounded-br-sm py-2 px-4 max-w-[80%]">
            <div class="whitespace-pre-wrap message-content"></div>
        </div>
    </div>
</template>

<template id="ai-message-template">
    <div class="flex justify-start mb-4 animate-fade-in">
        <div class="bg-muted text-foreground rounded-lg py-2 px-4 max-w-[80%] rounded-tl-xl rounded-tr-xl rounded-bl-sm rounded-br-xl">
            <div class="whitespace-pre-wrap message-content typing-animation"></div>
        </div>
    </div>
</template>

<template id="loading-message-template">
    <div class="flex justify-start mb-4 loading-message">
        <div class="bg-muted text-foreground rounded-lg py-3 px-4">
            <div class="flex items-center space-x-3">
                <div class="animate-spin h-5 w-5 text-foreground">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <div class="text-sm text-foreground ml-2 font-medium">Génération en cours...</div>
            </div>
        </div>
    </div>
</template>

<template id="error-message-template">
    <div class="flex justify-center mb-4">
        <div class="bg-destructive/10 border border-destructive text-destructive rounded-lg py-2 px-4 max-w-[80%]">
            <div class="whitespace-pre-wrap message-content"></div>
        </div>
    </div>
</template>

<template id="model-selection-message-template">
    <div id="model-selection-message" class=" p-4 my-4 text-center">
        <p class="text-foreground font-semibold text-lg mt-1 model-name"></p>
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

    /* Effet REFLET (shimmer) pour le label "Processus de réflexion" pendant la génération */
    @keyframes thinkShimmer {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }

    .think-reflect {
        position: relative;
        display: inline-block;
        /* on conserve la couleur du texte d'origine */
    }

    /* Calque reflet qui passe DANS les lettres */
    .think-reflect::before {
        content: attr(data-text);
        position: absolute;
        inset: 0;
        pointer-events: none;
        /* Dégradé qui balaye de gauche à droite */
        background-image: linear-gradient(110deg,
            rgba(255,255,255,0) 0%,
            rgba(255,255,255,0) 40%,
            rgba(255,255,255,0.9) 50%,
            rgba(255,255,255,0) 60%,
            rgba(255,255,255,0) 100%);
        background-size: 200% 100%;
        background-position: 200% 0; /* départ à droite */
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
        animation: thinkShimmer 1.6s ease-in-out infinite;
        will-change: background-position;
        /* Optionnel: léger éclaircissement du reflet par mélange */
        mix-blend-mode: screen;
    }

    @media (prefers-color-scheme: dark) {
        .think-reflect::before {
            background-image: linear-gradient(110deg,
                rgba(255,255,255,0) 0%,
                rgba(255,255,255,0) 40%,
                rgba(255,255,255,1) 50%,
                rgba(255,255,255,0) 60%,
                rgba(255,255,255,0) 100%);
        }
    }

    /* Supprimer tout ancien fallback (::after) */
    .think-reflect::after { content: none !important; }
</style>

<script>

// Auto‑scroll « toujours en bas » même après changement de conversation
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

// Gestion du streaming avec Server-Sent Events
// ------------------------------------------------------------

let currentEventSource = null;
let currentAIMessage = null;
let isUserScrolling = false;

// Renderer de streaming pour gérer les balises <think> en direct
let streamingRenderer = null;

function initStreamingRenderer(containerEl) {
    // containerEl est la div .message-content
    const state = {
        container: containerEl,
        mode: 'text', // 'text' | 'think'
        buffer: '',
        textDiv: null, // div pour texte normal (whitespace-pre-wrap)
        thinkDetails: null,
        thinkContentDiv: null,
    };

    const ensureTextDiv = () => {
        if (!state.textDiv || state.textDiv.parentNode !== state.container) {
            const div = document.createElement('div');
            div.className = 'whitespace-pre-wrap';
            state.container.appendChild(div);
            state.textDiv = div;
        }
    };

    const openThink = () => {
        // Ferme le buffer texte courant avant d'ouvrir
        flushText();
        state.mode = 'think';
        const details = document.createElement('details');
        details.className = 'my-2 group';
        const summary = document.createElement('summary');
        summary.className = 'cursor-pointer select-none text-sm text-gray-600 flex items-center gap-2';
        const thinkLabel = `${'{{ __("Processus de réflexion") }}'}`;
        summary.innerHTML = `
            <svg class="w-4 h-4 transition-transform duration-200 group-open:rotate-90" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
            <span class="think-reflect" data-text="${thinkLabel}">${thinkLabel}</span>
        `;
        const content = document.createElement('div');
        content.className = 'mt-2 border border-border bg-background text-foreground rounded-md p-3 whitespace-pre-wrap text-sm';
        details.appendChild(summary);
        details.appendChild(content);
        state.container.appendChild(details);
        state.thinkDetails = details;
        state.thinkContentDiv = content;
    };

    const closeThink = () => {
        state.mode = 'text';
        state.thinkDetails = null;
        state.thinkContentDiv = null;
    };

    const flushText = () => {
        if (!state.buffer) return;
        if (state.mode === 'think') {
            if (state.thinkContentDiv) state.thinkContentDiv.textContent += state.buffer;
        } else {
            ensureTextDiv();
            state.textDiv.textContent += state.buffer;
        }
        state.buffer = '';
    };

    const process = (chunk) => {
        let i = 0;
        while (i < chunk.length) {
            const openIdx = chunk.indexOf('<think>', i);
            const closeIdx = chunk.indexOf('</think>', i);

            if (state.mode === 'text') {
                if (openIdx === -1) {
                    state.buffer += chunk.slice(i);
                    i = chunk.length;
                } else {
                    // ajouter tout avant <think>
                    state.buffer += chunk.slice(i, openIdx);
                    flushText();
                    i = openIdx + 7; // longueur de '<think>'
                    openThink();
                }
            } else { // in think
                if (closeIdx === -1) {
                    state.buffer += chunk.slice(i);
                    i = chunk.length;
                } else {
                    // ajouter contenu jusqu'à </think>
                    state.buffer += chunk.slice(i, closeIdx);
                    flushText();
                    i = closeIdx + 8; // longueur de '</think>'
                    closeThink();
                }
            }
        }
        // Ne pas flushText ici pour conserver l'incrémentalité (éviter d'insérer des noeuds vides)
        flushText();
    };

    return { process };
}

// Fonction pour détecter si l'utilisateur scrolle manuellement
function setupScrollDetection() {
    const container = document.getElementById('chat-messages');
    if (!container) return;

    let scrollTimeout;
    container.addEventListener('scroll', () => {
        isUserScrolling = true;
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(() => {
            isUserScrolling = false;
        }, 1000);
    });
}

// Fonction pour faire défiler vers le bas seulement si l'utilisateur ne scrolle pas
function smartScrollToBottom() {
    if (!isUserScrolling) {
        const container = document.getElementById('chat-messages');
        if (container) {
            container.scrollTop = container.scrollHeight;
        }
    }
}

// Fonction pour créer un nouveau message AI dans l'interface
function createAIMessage() {
    const template = document.getElementById('ai-message-template');
    if (!template) return null;

    const messageElement = template.content.cloneNode(true);
    const container = document.getElementById('chat-messages');
    if (!container) return null;

    container.appendChild(messageElement);
    
    // Retourner l'élément de contenu pour pouvoir y ajouter du texte/DOM
    const messages = container.querySelectorAll('.message-content');
    const el = messages[messages.length - 1] || null;
    if (el) {
        streamingRenderer = initStreamingRenderer(el);
    }
    return el;
}

// Fonction pour ajouter un message de chargement
function showLoadingMessage() {
    const template = document.getElementById('loading-message-template');
    if (!template) return null;

    const loadingElement = template.content.cloneNode(true);
    const container = document.getElementById('chat-messages');
    if (!container) return null;

    container.appendChild(loadingElement);
    smartScrollToBottom();
    
    return container.querySelector('.loading-message');
}

// Fonction pour supprimer le message de chargement
function hideLoadingMessage() {
    const loadingMessage = document.querySelector('.loading-message');
    if (loadingMessage) {
        loadingMessage.remove();
    }
}

// Fonction pour gérer le streaming
function handleStreaming(data) {
    // Afficher le message de chargement
    showLoadingMessage();

    // Préparer les données pour l'envoi
    const streamData = {
        model: data.model,
        messages: data.messages,
        conversationId: data.conversationId,
        ragEnabled: data.ragEnabled,
        selectedCollection: data.selectedCollection,
        temperature: data.temperature,
        maxTokens: data.maxTokens
    };

    // Démarrer le polling périodique des messages
    let pollingInterval;
    const startPolling = () => {
        pollingInterval = setInterval(() => {
            if (window.Livewire) {
                window.Livewire.dispatch('refreshMessages');
            }
        }, 2000); // Polling toutes les secondes
    };

    const stopPolling = () => {
        if (pollingInterval) {
            clearInterval(pollingInterval);
            pollingInterval = null;
        }
    };

    fetch('/api/chat/stream', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify(streamData)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        // Supprimer le message de chargement et créer le message AI
        hideLoadingMessage();
        currentAIMessage = createAIMessage();
        
        if (!currentAIMessage) {
            throw new Error('Impossible de créer le message AI');
        }
        
        // Démarrer le polling
        startPolling();

        // Lire le stream
        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        function readStream() {
            return reader.read().then(({ done, value }) => {
                if (done) {
                    // Arrêter le polling et faire un dernier refresh
                    stopPolling();
                    setTimeout(() => {
                        // Nettoyer le message temporaire
                        if (currentAIMessage) {
                            const wrapper = currentAIMessage.closest('.flex');
                            if (wrapper) {
                                wrapper.remove();
                            }
                        }
                        currentAIMessage = null;
                        streamingRenderer = null;
                        hideLoadingMessage();
                    }, 500);
                    
                    return;
                }

                buffer += decoder.decode(value, { stream: true });
                const lines = buffer.split('\n');
                buffer = lines.pop() || ''; // Garder la ligne incomplète

                lines.forEach(line => {
                    line = line.trim();
                    if (line.startsWith('data: ')) {
                        try {
                            const data = JSON.parse(line.substring(6));
                            handleSSEEvent(data);
                        } catch (e) {
                            console.error('Erreur parsing JSON:', e, line);
                        }
                    }
                });

                return readStream();
            });
        }

        return readStream();
    })
    .catch(error => {
        console.error('Erreur lors du streaming:', error);
        stopPolling();
        hideLoadingMessage();
        
        // Afficher un message d'erreur
        if (window.Livewire) {
            window.Livewire.dispatch('messageAdded', {
                role: 'error',
                content: 'Erreur lors du streaming: ' + error.message
            });
        }
    });
}

// Fonction pour traiter les événements SSE
function handleSSEEvent(data) {
    switch (data.event || 'chunk') {
        case 'chunk':
            if (data.content) {
                // Si le noeud temporaire a été perdu (re-render Livewire), le recréer
                if (!currentAIMessage) {
                    currentAIMessage = createAIMessage();
                }
                if (!streamingRenderer && currentAIMessage) {
                    streamingRenderer = initStreamingRenderer(currentAIMessage);
                }
                if (currentAIMessage && streamingRenderer) {
                    streamingRenderer.process(data.content);
                    smartScrollToBottom();
                } else {
                    console.warn('chunk reçu mais currentAIMessage est introuvable — message non affiché');
                }
            }
            break;

        case 'complete':
            // Retirer l'effet reflet avant nettoyage
            try {
                if (currentAIMessage) {
                    const wrapper = currentAIMessage.closest('.flex');
                    if (wrapper) wrapper.querySelectorAll('.think-reflect').forEach(el => el.classList.remove('think-reflect'));
                }
            } catch {}
            // Injecter immédiatement le message final dans l'état Livewire
            try {
                const finalText = (typeof data.response === 'string' && data.response.length)
                    ? data.response
                    : (currentAIMessage?.textContent || '');
                if (finalText && window.Livewire) {
                    window.Livewire.dispatch('messageAdded', {
                        role: 'assistant',
                        content: finalText,
                    });
                    
                    // Attendre un court délai pour que Livewire traite le message
                    setTimeout(() => {
                        // Nettoyer le message temporaire après que Livewire ait eu le temps de se re-render
                        if (currentAIMessage) {
                            const wrapper = currentAIMessage.closest('.flex');
                            if (wrapper) {
                                wrapper.remove();
                            }
                        }
                        currentAIMessage = null;
                        streamingRenderer = null;
                        hideLoadingMessage();
                    }, 100);
                }
            } catch (e) {
                console.warn('Impossible d\'injecter le message final dans Livewire:', e);
            }
            
            // Notifier Livewire pour la persistance (tokens, etc.)
            if (window.Livewire && data.conversationId) {
                window.Livewire.dispatch('saveAssistantMessage', {
                    conversationId: data.conversationId,
                    content: data.response,
                    tokens: data.tokens || 0
                });
            }
            
            break;

        case 'error':
            // Retirer l'effet reflet
            try {
                if (currentAIMessage) {
                    const wrapper = currentAIMessage.closest('.flex');
                    if (wrapper) wrapper.querySelectorAll('.think-reflect').forEach(el => el.classList.remove('think-reflect'));
                }
            } catch {}
            console.error('Erreur SSE:', data.message);
            hideLoadingMessage();
            
            if (window.Livewire) {
                window.Livewire.dispatch('messageAdded', {
                    role: 'error',
                    content: data.message
                });
            }
            
            currentAIMessage = null;
            streamingRenderer = null;
            break;

        case 'close':
            // Retirer l'effet reflet
            try {
                if (currentAIMessage) {
                    const wrapper = currentAIMessage.closest('.flex');
                    if (wrapper) wrapper.querySelectorAll('.think-reflect').forEach(el => el.classList.remove('think-reflect'));
                }
            } catch {}
            console.log('Connexion SSE fermée');
            currentAIMessage = null;
            streamingRenderer = null;
            break;
    }
}

// Écouter l'événement de démarrage du streaming depuis Livewire
document.addEventListener('livewire:initialized', () => {
    setupScrollDetection();
    
    // Écouter l'événement startStreaming
    Livewire.on('startStreaming', (event) => {
        const data = Array.isArray(event) ? event[0] : event;
        handleStreaming(data);
    });
    
    // Écouter l'événement messageAdded pour afficher les messages utilisateur
    Livewire.on('messageAdded', (event) => {
        const data = Array.isArray(event) ? event[0] : event;
        
        if (data.role === 'user') {
            // Créer un nouveau message utilisateur
            const template = document.getElementById('user-message-template');
            if (template) {
                const messageElement = template.content.cloneNode(true);
                const contentElement = messageElement.querySelector('.message-content');
                if (contentElement) {
                    contentElement.textContent = data.content;
                }
                
                const container = document.getElementById('chat-messages');
                if (container) {
                    container.appendChild(messageElement);
                    smartScrollToBottom();
                }
            }
        } else if (data.role === 'error') {
            // Créer un message d'erreur
            const template = document.getElementById('error-message-template');
            if (template) {
                const messageElement = template.content.cloneNode(true);
                const contentElement = messageElement.querySelector('.message-content');
                if (contentElement) {
                    contentElement.textContent = data.content;
                }
                
                const container = document.getElementById('chat-messages');
                if (container) {
                    container.appendChild(messageElement);
                    smartScrollToBottom();
                }
            }
        }
    });
});

// Fallback pour la détection de scroll seulement
document.addEventListener('DOMContentLoaded', () => {
    setupScrollDetection();
});

</script>
