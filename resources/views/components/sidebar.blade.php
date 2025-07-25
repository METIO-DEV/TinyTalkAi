<!-- Composant pour la sidebar -->
<div class="bg-custom-light dark:bg-custom-white-dark-mode dark:text-custom-white h-full w-full pl-6 pt-6 pb-6 flex flex-col overflow-hidden">
    <div class="mb-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-custom-black dark:text-custom-white">TinyTalk AI</h2>
            <div class="flex items-center">
                <div class="relative" id="profile-dropdown">
                    <button id="profile-dropdown-button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-custom-black bg-custom-white dark:bg-custom-light-dark-mode dark:text-custom-white hover:bg-custom-light-dark-mode dark:hover:bg-custom-mid-dark-mode focus:outline-none transition ease-in-out duration-150">
                        <span>{{ auth()->user()->name }}</span>
                        <div class="ms-1">
                            <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </div>
                    </button>

                    <div id="profile-dropdown-menu" class="hidden absolute z-50 mt-2 w-48 rounded-md shadow-lg origin-top-right right-0">
                        <div class="rounded-md ring-1 ring-black ring-opacity-5 bg-custom-white dark:bg-custom-light-dark-mode">
                            <a href="{{ route('profile') }}" class="flex justify-center items-center px-4 py-2 text-sm text-custom-black dark:text-custom-white rounded-md hover:bg-custom-mid-dark-mode dark:hover:bg-custom-mid-dark-mode">
                                {{ __('Profile') }}
                            </a>
                            <form method="POST" action="{{ route('logout') }}" id="logout-form">
                                @csrf
                                <x-danger-button type="button" id="logout-button" class="w-full flex justify-center items-center px-4 py-2 text-sm text-custom-black dark:text-custom-white rounded-md">
                                    {{ __('Log Out') }}
                                </x-danger-button>  
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Section Modèles -->
    <div class="mb-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-custom-black dark:text-custom-white uppercase tracking-wider">
                <button class="flex items-center w-full text-left focus:outline-none" data-collapse-toggle="models-section">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 transform transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                    Modèles
                </button>
            </h3>
        </div>
        <div id="models-section" class="space-y-2">
            <x-model-list :models="$models" :selectedModel="$selectedModel ?? null" /> <!-- Composant pour la liste des modèles -->
        </div>
    </div>
    
    <!-- Section Réglages -->
    <div class="mb-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-custom-black dark:text-custom-white uppercase tracking-wider">
                <button class="flex items-center w-full text-left focus:outline-none" data-collapse-toggle="settings-section">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 transform transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                    Réglages
                </button>
            </h3>
        </div>
        <div id="settings-section" class="space-y-2 overflow-y-auto max-h-48">
            <x-settings-panel :temperature="$temperature ?? 0.7" :maxTokens="$maxTokens ?? 1024" />
        </div>
    </div>

    
    
    <!-- Section Historique -->
    <div class="mb-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-custom-black dark:text-custom-white uppercase tracking-wider">
                <button class="flex items-center text-left focus:outline-none" data-collapse-toggle="history-section">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 transform transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                    Historique
                </button>
            </h3>
            <!-- Bouton Nouvelle conversation -->
            <button id="new-conversation-btn" class="flex items-center justify-center text-custom-black dark:text-custom-white hover:bg-custom-light dark:hover:bg-custom-mid-dark-mode rounded-md p-1.5 transition-colors duration-200 border border-gray-300 dark:border-gray-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m6-6H6" />
                </svg>
            </button>
        </div>
        <div class="relative">
            <!-- Indicateur de défilement vers le haut -->
            <div id="scroll-up-indicator" class=" absolute top-0 left-0 right-0 h-6 bg-gradient-to-b  to-transparent z-10 flex justify-center items-start">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-custom-black dark:text-custom-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                </svg>
            </div>
            
            <div id="history-section" class="[&::-webkit-scrollbar]:w-0 space-y-2 overflow-y-auto max-h-48 bg-custom-white dark:bg-custom-light-dark-mode rounded-lg">
                @if(isset($conversations) && count($conversations) > 0)
                    @foreach($conversations as $conversation)
                        <div class="conversation-item px-3 py-2 rounded-md hover:bg-custom-light dark:hover:bg-custom-mid-dark-mode cursor-pointer transition-colors duration-200 flex justify-between items-center" 
                             data-conversation-id="{{ $conversation->id }}"
                             data-model="{{ $conversation->model_name }}">
                            <div class="flex-grow overflow-hidden">
                                <div class="text-sm text-custom-black dark:text-custom-white font-medium truncate">
                                    {{ $conversation->title ?? 'Conversation du ' . $conversation->created_at->format('d/m/Y') }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 flex flex-row justify-between">
                                    <p>{{ $conversation->model_name }}</p>  
                                </div>
                            </div>
                            <button class="delete-conversation-btn ml-2 text-gray-500 hover:text-red-500 dark:text-gray-400 dark:hover:text-red-400 transition-colors duration-200" 
                                    data-conversation-id="{{ $conversation->id }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    @endforeach
                @else
                    <div class="text-sm text-gray-500 dark:text-gray-300 px-3 py-2">
                        Aucune conversation enregistrée
                    </div>
                @endif
            </div>
            
            <!-- Indicateur de défilement vers le bas -->
            <div id="scroll-down-indicator" class="absolute bottom-0 left-0 right-0 h-6 bg-gradient-to-t to-transparent z-10 flex justify-center items-end">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-custom-black dark:text-custom-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </div>
        </div>
    </div>
</div>

<script>
    const profileDropdownButton = document.getElementById('profile-dropdown-button');
    const profileDropdownMenu = document.getElementById('profile-dropdown-menu');
    const logoutButton = document.getElementById('logout-button');
    const logoutForm = document.getElementById('logout-form');

    profileDropdownButton.addEventListener('click', () => {
        profileDropdownMenu.classList.toggle('hidden');
    });

    document.addEventListener('click', (e) => {
        if (!profileDropdownButton.contains(e.target) && !profileDropdownMenu.contains(e.target)) {
            profileDropdownMenu.classList.add('hidden');
        }
    });

    logoutButton.addEventListener('click', () => {
        if (confirm('Êtes-vous sûr de vouloir vous déconnecter ?')) {
            logoutForm.submit();
        }
    });

    // Gestion des indicateurs de défilement pour l'historique
    const historySection = document.getElementById('history-section');
    const scrollUpIndicator = document.getElementById('scroll-up-indicator');
    const scrollDownIndicator = document.getElementById('scroll-down-indicator');
    
    if (historySection && scrollUpIndicator && scrollDownIndicator) {
        historySection.addEventListener('scroll', function() {
            // Afficher l'indicateur de défilement vers le haut si on n'est pas tout en haut
            if (historySection.scrollTop > 10) {
                scrollUpIndicator.classList.remove('hidden');
            } else {
                scrollUpIndicator.classList.add('hidden');
            }
            
            // Afficher l'indicateur de défilement vers le bas si on n'est pas tout en bas
            if (historySection.scrollHeight - historySection.scrollTop - historySection.clientHeight > 10) {
                scrollDownIndicator.classList.remove('hidden');
            } else {
                scrollDownIndicator.classList.add('hidden');
            }
        });
        
        // Déclencher l'événement de défilement au chargement pour initialiser les indicateurs
        historySection.dispatchEvent(new Event('scroll'));
    }
    
    // Gestion des boutons de suppression des conversations
    document.querySelectorAll('.delete-conversation-btn').forEach(button => {
        button.addEventListener('click', function(event) {
            // Empêcher la propagation de l'événement pour éviter d'ouvrir la conversation
            event.stopPropagation();
            
            const conversationId = this.getAttribute('data-conversation-id');
            
            if (confirm('Êtes-vous sûr de vouloir supprimer cette conversation ?')) {
                // Appel AJAX pour supprimer la conversation
                fetch(`/api/conversation/${conversationId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => {
                    // Vérifier d'abord si la réponse est OK (statut 2xx)
                    if (!response.ok) {
                        throw new Error(`Erreur HTTP: ${response.status}`);
                    }
                    
                    // Vérifier si la réponse contient du contenu
                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.includes('application/json')) {
                        return response.json();
                    } else {
                        // Si la réponse n'est pas du JSON, on considère quand même que c'est un succès
                        return { success: true, message: 'Conversation supprimée' };
                    }
                })
                .then(data => {
                    // Supprimer l'élément du DOM, que la réponse soit un JSON valide ou non
                    const conversationItem = this.closest('.conversation-item');
                    conversationItem.remove();
                    
                    // Vérifier si c'était la conversation active en utilisant la variable globale dans chat.js
                    // Accéder à la variable currentConversationId définie dans chat.js
                    if (window.currentConversationId === conversationId) {
                        // Si c'était la conversation active, vider l'interface de chat
                        const chatMessages = document.getElementById('chat-messages');
                        if (chatMessages) {
                            chatMessages.innerHTML = '';
                            
                            // Ajouter un message indiquant que la conversation a été supprimée
                            const infoMessage = document.createElement('div');
                            infoMessage.className = 'flex justify-center mb-4';
                            infoMessage.innerHTML = `
                                <div class="bg-custom-light text-custom-black rounded-lg py-2 px-4 text-center">
                                    <p>Cette conversation a été supprimée.</p>
                                    <p class="text-sm mt-2">Sélectionnez une autre conversation ou commencez-en une nouvelle.</p>
                                </div>
                            `;
                            chatMessages.appendChild(infoMessage);
                            
                            // Désactiver la zone de saisie
                            const chatInput = document.getElementById('chat-input');
                            const sendButton = document.getElementById('send-button');
                            if (chatInput) chatInput.disabled = true;
                            if (sendButton) sendButton.disabled = true;
                            if (chatInput) chatInput.placeholder = "Sélectionnez un modèle...";
                            
                            // Réinitialiser l'ID de conversation courante
                            window.currentConversationId = null;
                        }
                    }
                    
                    // Afficher un message si la liste est vide
                    if (document.querySelectorAll('.conversation-item').length === 0) {
                        const emptyMessage = document.createElement('div');
                        emptyMessage.className = 'text-sm text-gray-500 dark:text-gray-300 px-3 py-2';
                        emptyMessage.textContent = 'Aucune conversation enregistrée';
                        historySection.appendChild(emptyMessage);
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    
                    // Même en cas d'erreur, on supprime l'élément car la suppression a probablement réussi côté serveur
                    const conversationItem = this.closest('.conversation-item');
                    if (conversationItem) {
                        conversationItem.remove();
                        
                        // Afficher un message si la liste est vide
                        if (document.querySelectorAll('.conversation-item').length === 0) {
                            const emptyMessage = document.createElement('div');
                            emptyMessage.className = 'text-sm text-gray-500 dark:text-gray-300 px-3 py-2';
                            emptyMessage.textContent = 'Aucune conversation enregistrée';
                            historySection.appendChild(emptyMessage);
                        }
                    }
                });
            }
        });
    });
</script>
