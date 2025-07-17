<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('AI Chat') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <!-- Chat container -->
                <div class="flex flex-col h-[calc(100vh-250px)]">
                    <!-- Model selector -->
                    <div class="mb-4">
                        <label for="model-selector" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Sélectionnez un modèle :</label>
                        <div class="flex gap-2 flex-wrap" id="model-buttons">
                            @if(isset($models) && count($models) > 0)
                                @foreach($models as $model)
                                    <button type="button" 
                                        class="model-button px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700" 
                                        data-model="{{ $model['name'] }}">
                                        {{ $model['name'] }}
                                        <span class="text-xs ml-1 text-gray-500">({{ isset($model['details']['parameter_size']) ? $model['details']['parameter_size'] : 'N/A' }})</span>
                                    </button>
                                @endforeach
                            @else
                                <div class="text-red-500">Aucun modèle disponible. Assurez-vous qu'Ollama est en cours d'exécution.</div>
                            @endif
                        </div>
                    </div>

                    <!-- Parameters -->
                    <div class="mb-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="temperature" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Température: <span id="temp-value">0.7</span></label>
                            <input type="range" id="temperature" min="0" max="2" step="0.1" value="0.7" 
                                class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer dark:bg-gray-700">
                        </div>
                        <div>
                            <label for="max-tokens" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Max Tokens: <span id="tokens-value">1024</span></label>
                            <input type="range" id="max-tokens" min="256" max="4096" step="128" value="1024" 
                                class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer dark:bg-gray-700">
                        </div>
                    </div>

                    <!-- Chat messages -->
                    <div id="chat-messages" class="flex-1 overflow-y-auto mb-4 space-y-4 p-2 border border-gray-200 dark:border-gray-700 rounded-lg">
                        <div class="text-center text-gray-500 dark:text-gray-400 py-8">
                            Sélectionnez un modèle et commencez à discuter
                        </div>
                    </div>
                    
                    <!-- Chat input -->
                    <div class="mt-auto">
                        <form id="chat-form" class="flex">
                            <input id="chat-input" type="text" class="flex-1 border border-gray-300 dark:border-gray-600 rounded-l-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:text-white" placeholder="Écrivez votre message ici..." disabled />
                            <button type="submit" id="send-button" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-r-lg disabled:bg-blue-400 disabled:cursor-not-allowed" disabled>
                                Envoyer
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
        
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Variables
            let selectedModel = null;
            const chatMessages = document.getElementById('chat-messages');
            const chatForm = document.getElementById('chat-form');
            const chatInput = document.getElementById('chat-input');
            const sendButton = document.getElementById('send-button');
            const modelButtons = document.querySelectorAll('.model-button');
            const temperatureSlider = document.getElementById('temperature');
            const maxTokensSlider = document.getElementById('max-tokens');
            const tempValue = document.getElementById('temp-value');
            const tokensValue = document.getElementById('tokens-value');
            
            // Initialiser les sliders
            temperatureSlider.addEventListener('input', function() {
                tempValue.textContent = this.value;
            });
            
            maxTokensSlider.addEventListener('input', function() {
                tokensValue.textContent = this.value;
            });
            
            // Sélection du modèle
            modelButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Désélectionner tous les boutons
                    modelButtons.forEach(btn => {
                        btn.classList.remove('bg-blue-100', 'dark:bg-blue-900', 'border-blue-500');
                        btn.classList.add('border-gray-300', 'dark:border-gray-600');
                    });
                    
                    // Sélectionner le bouton actuel
                    this.classList.add('bg-blue-100', 'dark:bg-blue-900', 'border-blue-500');
                    this.classList.remove('border-gray-300', 'dark:border-gray-600');
                    
                    // Stocker le modèle sélectionné
                    selectedModel = this.dataset.model;
                    
                    // Activer le formulaire de chat
                    chatInput.disabled = false;
                    sendButton.disabled = false;
                    
                    // Effacer les messages précédents
                    chatMessages.innerHTML = `<div class="text-center text-gray-500 dark:text-gray-400 py-4">
                        Modèle <span class="font-semibold">${selectedModel}</span> sélectionné. Commencez à discuter!
                    </div>`;
                });
            });
            
            // Envoi du message
            chatForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                if (!selectedModel || !chatInput.value.trim()) return;
                
                const userMessage = chatInput.value.trim();
                
                // Ajouter le message de l'utilisateur
                addMessage('user', userMessage);
                
                // Effacer l'input
                chatInput.value = '';
                
                // Désactiver le formulaire pendant le chargement
                chatInput.disabled = true;
                sendButton.disabled = true;
                sendButton.innerHTML = `<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg> Envoi...`;
                
                // Ajouter un message de chargement
                const loadingMessageId = 'loading-' + Date.now();
                chatMessages.innerHTML += `
                    <div id="${loadingMessageId}" class="flex items-start">
                        <div class="flex-shrink-0 bg-blue-500 text-white p-2 rounded-lg">AI</div>
                        <div class="ml-3 bg-gray-100 dark:bg-gray-700 p-3 rounded-lg max-w-[80%]">
                            <div class="animate-pulse">Génération de la réponse...</div>
                        </div>
                    </div>
                `;
                
                // Faire défiler vers le bas
                chatMessages.scrollTop = chatMessages.scrollHeight;
                
                // Envoyer la requête à l'API
                fetch('/chat/send', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        message: userMessage,
                        model: selectedModel,
                        temperature: parseFloat(temperatureSlider.value),
                        max_tokens: parseInt(maxTokensSlider.value)
                    })
                })
                .then(response => response.json())
                .then(data => {
                    // Supprimer le message de chargement
                    document.getElementById(loadingMessageId).remove();
                    
                    if (data.success) {
                        // Ajouter la réponse du modèle
                        addMessage('ai', data.response);
                    } else {
                        // Afficher l'erreur
                        addErrorMessage(data.error || 'Une erreur est survenue');
                    }
                })
                .catch(error => {
                    // Supprimer le message de chargement
                    document.getElementById(loadingMessageId).remove();
                    
                    // Afficher l'erreur
                    addErrorMessage('Erreur de communication avec le serveur');
                    console.error('Error:', error);
                })
                .finally(() => {
                    // Réactiver le formulaire
                    chatInput.disabled = false;
                    sendButton.disabled = false;
                    sendButton.textContent = 'Envoyer';
                });
            });
            
            // Fonction pour ajouter un message au chat
            function addMessage(sender, content) {
                const messageElement = document.createElement('div');
                messageElement.className = 'flex items-start';
                
                if (sender === 'user') {
                    messageElement.innerHTML = `
                        <div class="flex-shrink-0 bg-green-500 text-white p-2 rounded-lg">Vous</div>
                        <div class="ml-3 bg-green-50 dark:bg-green-900 p-3 rounded-lg max-w-[80%]">
                            <div class="whitespace-pre-wrap">${escapeHtml(content)}</div>
                        </div>
                    `;
                } else {
                    messageElement.innerHTML = `
                        <div class="flex-shrink-0 bg-blue-500 text-white p-2 rounded-lg">AI</div>
                        <div class="ml-3 bg-gray-100 dark:bg-gray-700 p-3 rounded-lg max-w-[80%]">
                            <div class="whitespace-pre-wrap">${escapeHtml(content)}</div>
                        </div>
                    `;
                }
                
                chatMessages.appendChild(messageElement);
                
                // Faire défiler vers le bas
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
            
            // Fonction pour ajouter un message d'erreur
            function addErrorMessage(errorText) {
                const messageElement = document.createElement('div');
                messageElement.className = 'flex items-start';
                messageElement.innerHTML = `
                    <div class="flex-shrink-0 bg-red-500 text-white p-2 rounded-lg">Erreur</div>
                    <div class="ml-3 bg-red-50 dark:bg-red-900 p-3 rounded-lg max-w-[80%]">
                        <div class="text-red-700 dark:text-red-300">${escapeHtml(errorText)}</div>
                    </div>
                `;
                
                chatMessages.appendChild(messageElement);
                
                // Faire défiler vers le bas
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
            
            // Fonction pour échapper le HTML
            function escapeHtml(unsafe) {
                return unsafe
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;");
            }
        });
    </script>
</x-app-layout>