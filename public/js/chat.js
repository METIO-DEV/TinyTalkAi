/**
 * TinyTalk AI - Script principal pour l'interface de chat
 * 
 * Ce script gère toutes les interactions de l'interface de chat :
 * - Sélection des modèles
 * - Ajustement des paramètres (température, max_tokens)
 * - Envoi et réception des messages
 * - Gestion des sections pliables dans la sidebar
 */
document.addEventListener('DOMContentLoaded', function() {
    // Variables
    const chatMessages = document.getElementById('chat-messages');
    const chatForm = document.getElementById('chat-form');
    const chatInput = document.getElementById('chat-input');
    const sendButton = document.getElementById('send-button');
    const modelButtons = document.querySelectorAll('.model-button');
    const temperatureSlider = document.getElementById('temperature');
    const tempValue = document.getElementById('temperature');
    const maxTokensSlider = document.getElementById('max-tokens');
    const tokensValue = document.getElementById('tokens-value');
    const conversationItems = document.querySelectorAll('.conversation-item');
    
    // Variable pour stocker l'ID de la conversation actuelle
    let currentConversationId = null;
    
    // Initialisation des sliders
    if (temperatureSlider) {
        temperatureSlider.addEventListener('input', function() {
            tempValue.textContent = this.value;
        });
    }
    
    if (maxTokensSlider) {
        maxTokensSlider.addEventListener('input', function() {
            tokensValue.textContent = this.value;
        });
    }
    
    // Initialiser les gestionnaires d'événements pour les éléments de conversation
    initConversationItemHandlers();
    
    // Initialiser le gestionnaire pour le bouton "Nouvelle conversation"
    initNewConversationButton();
    
    // Fonction pour initialiser les gestionnaires d'événements sur les éléments de conversation
    function initConversationItemHandlers() {
        const conversationItems = document.querySelectorAll('.conversation-item');
        conversationItems.forEach(item => {
            item.addEventListener('click', function() {
                // Récupérer l'ID de la conversation et le modèle
                const conversationId = this.dataset.conversationId;
                const modelName = this.dataset.model;
                
                // Sauvegarder l'ID de conversation actuelle
                currentConversationId = conversationId;
                
                // Sélectionner automatiquement le modèle associé à cette conversation
                localStorage.setItem('selectedModel', modelName);
                
                // Mettre à jour l'apparence des boutons de modèle
                updateModelButtonsAppearance();
                
                // Mettre à jour le placeholder du textarea
                if (chatInput) {
                    chatInput.placeholder = `Écrivez à ${modelName}...`;
                }
                
                // Activer le formulaire
                if (chatInput) chatInput.disabled = false;
                if (sendButton) sendButton.disabled = false;
                
                // Charger l'historique de la conversation
                loadConversationHistory(conversationId);
                
                // Mettre en évidence la conversation sélectionnée
                document.querySelectorAll('.conversation-item').forEach(item => {
                    item.classList.remove('bg-custom-light-dark-mode');
                });
                this.classList.add('bg-custom-light-dark-mode');
            });
        });
    }
    
    // Fonction pour initialiser le bouton "Nouvelle conversation"
    function initNewConversationButton() {
        const newConversationBtn = document.getElementById('new-conversation-btn');
        if (newConversationBtn) {
            newConversationBtn.addEventListener('click', function() {
                // Réinitialiser l'ID de conversation courante
                currentConversationId = null;
                
                // Effacer les messages précédents
                if (chatMessages) {
                    chatMessages.innerHTML = '';
                }
                
                // Mettre à jour l'apparence des éléments de conversation
                document.querySelectorAll('.conversation-item').forEach(item => {
                    item.classList.remove('bg-custom-light-dark-mode');
                });
                
                // Activer le formulaire
                if (chatInput) chatInput.disabled = false;
                if (sendButton) sendButton.disabled = false;
                
                // Récupérer le modèle sélectionné
                const selectedModel = localStorage.getItem('selectedModel');
                
                // Mettre à jour le placeholder
                if (chatInput && selectedModel) {
                    chatInput.placeholder = `Écrivez à ${selectedModel}...`;
                }
                
                // Afficher un message de sélection du modèle
                if (chatMessages && selectedModel) {
                    showModelSelectionMessage(selectedModel);
                }
            });
        }
    }
    
    // Gestion des sections pliables dans la sidebar avec animation
    document.querySelectorAll('[data-collapse-toggle]').forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-collapse-toggle');
            const targetElement = document.getElementById(targetId);
            const icon = this.querySelector('svg');
            
            // Gestion de la rotation de l'icône directement avec style.transform
            if (icon.style.transform === 'rotate(-90deg)') {
                icon.style.transform = 'rotate(0deg)';
            } else {
                icon.style.transform = 'rotate(-90deg)';
            }
            
            if (targetElement.classList.contains('hidden')) {
                // Animation d'ouverture
                targetElement.classList.remove('hidden');
                targetElement.style.maxHeight = '0';
                targetElement.style.opacity = '0';
                targetElement.style.overflow = 'hidden';
                
                // Force un reflow pour que la transition fonctionne
                void targetElement.offsetWidth;
                
                // Applique l'animation
                targetElement.style.transition = 'max-height 0.3s ease-in-out, opacity 0.3s ease-in-out';
                targetElement.style.maxHeight = targetElement.scrollHeight + 'px';
                targetElement.style.opacity = '1';
                
                // Nettoie après l'animation
                setTimeout(() => {
                    targetElement.style.overflow = '';
                    targetElement.style.maxHeight = '';
                }, 300);
            } else {
                // Animation de fermeture
                targetElement.style.maxHeight = targetElement.scrollHeight + 'px';
                targetElement.style.overflow = 'hidden';
                
                // Force un reflow
                void targetElement.offsetWidth;
                
                // Applique l'animation
                targetElement.style.transition = 'max-height 0.3s ease-in-out, opacity 0.3s ease-in-out';
                targetElement.style.maxHeight = '0';
                targetElement.style.opacity = '0';
                
                // Cache l'élément une fois l'animation terminée
                setTimeout(() => {
                    targetElement.classList.add('hidden');
                    targetElement.style.overflow = '';
                    targetElement.style.maxHeight = '';
                    targetElement.style.opacity = '';
                }, 300);
            }
        });
    });
    
    // Fonction pour afficher le message de confirmation
    function showModelSelectionMessage(selectedModel) {
        if (!chatMessages) return;
        
        // Vérifier d'abord s'il y a déjà un message de confirmation
        const existingMessage = document.getElementById('model-selection-message');
        if (existingMessage) {
            existingMessage.remove();
        }
        
        // Utiliser le template pour créer le message de confirmation
        const template = document.getElementById('model-selection-message-template');
        if (!template) {
            console.warn('Template model-selection-message-template non trouvé');
            return; // Sortir de la fonction si le template n'existe pas
        }
        
        try {
            const clone = document.importNode(template.content, true);
            
            // Définir le nom du modèle
            const modelNameElement = clone.querySelector('.model-name');
            if (modelNameElement) {
                modelNameElement.textContent = selectedModel;
            }
            
            // Insérer au début du conteneur de messages
            if (chatMessages.firstChild) {
                chatMessages.insertBefore(clone, chatMessages.firstChild);
            } else {
                chatMessages.appendChild(clone);
            }
        } catch (error) {
            console.error('Erreur lors de l\'affichage du message de sélection du modèle:', error);
        }
    }
    
    // Fonction pour mettre à jour l'apparence des boutons de modèle
    function updateModelButtonsAppearance() {
        const selectedModel = localStorage.getItem('selectedModel');
        
        if (selectedModel) {
            // Afficher le message de confirmation
            showModelSelectionMessage(selectedModel);
            
            // Activer le formulaire de chat
            if (chatInput) {
                chatInput.disabled = false;
                sendButton.disabled = false;
            }
        }
        
        let selectedButton = null;
        let selectedModelSize = '';
        
        modelButtons.forEach(btn => {
            const isSelected = btn.dataset.model === selectedModel;
            
            // Réinitialiser tous les styles possibles
            btn.classList.remove('bg-custom-white', 'bg-custom-black', 'bg-custom-mid', 'bg-custom-light');
            btn.classList.add('bg-custom-mid');
            
            // S'assurer que le texte est toujours noir (par défaut)    
            const textElement = btn.querySelector('div');
            if (textElement) {
                textElement.className = 'font-medium text-custom-black';
            }
            
            // Appliquer les styles pour le bouton sélectionné
            if (isSelected) {
                btn.classList.remove('bg-custom-mid');
                btn.classList.add('bg-custom-white');
                btn.style.borderColor = '#000000';
                selectedButton = btn;
                selectedModelSize = btn.dataset.size || '';
                
                // Mettre à jour le placeholder avec le nom du modèle
                if (chatInput && textElement) {
                    // Extraire le nom du modèle (partie avant le ":")
                    let displayName = textElement.textContent.trim();
                    if (displayName.includes(':')) {
                        displayName = displayName.split(':')[0];
                    }
                    chatInput.placeholder = `Écrivez à ${displayName}...`;
                }
                
                // Mettre à jour le message de confirmation avec la taille du modèle
                if (selectedModelSize) {
                    const existingMessage = document.getElementById('model-selection-message');
                    if (existingMessage) {
                        const modelNameElement = existingMessage.querySelector('.model-name');
                        if (modelNameElement) {
                            // Formater la taille en GB si possible
                            const sizeInBytes = parseInt(selectedModelSize);
                            let formattedSize = '';
                            if (!isNaN(sizeInBytes)) {
                                const sizeInGB = (sizeInBytes / (1024 * 1024 * 1024)).toFixed(2);
                                formattedSize = `(${sizeInGB} GB)`;
                            }
                            
                            // Extraire le nom du modèle et la version
                            let modelName = selectedModel;
                            let modelVersion = '';
                            
                            if (selectedModel.includes(':')) {
                                const parts = selectedModel.split(':');
                                modelName = parts[0];
                                modelVersion = parts[1];
                            }
                            
                            // Mettre à jour le texte avec le nom, la version et la taille
                            modelNameElement.textContent = `${modelName}:${modelVersion} ${formattedSize}`;
                        }
                    }
                }
            } else {
                btn.style.borderColor = '';
            }
        });
        
        // Faire défiler jusqu'au modèle sélectionné
        if (selectedButton) {
            // Trouver le conteneur parent avec défilement
            const modelListContainer = document.getElementById('model-container');
            if (modelListContainer) {
                // Calculer la position du bouton sélectionné par rapport au conteneur
                const containerRect = modelListContainer.getBoundingClientRect();
                const buttonRect = selectedButton.getBoundingClientRect();
                
                // Calculer la position de défilement optimale
                const scrollTop = selectedButton.offsetTop - modelListContainer.offsetTop - (containerRect.height / 2) + (buttonRect.height / 2);
                
                // Appliquer le défilement avec une animation fluide
                modelListContainer.scrollTo({
                    top: Math.max(0, scrollTop),
                    behavior: 'smooth'
                });
            }
        }
    }
    
    // Gestion des boutons de modèle
    modelButtons.forEach(button => {
        // Gestion du clic
        button.addEventListener('click', function() {
            localStorage.setItem('selectedModel', this.dataset.model);
            
            // Réinitialiser l'ID de conversation actuelle lors du changement de modèle
            currentConversationId = null;
            
            // Mettre à jour le placeholder du textarea
            if (chatInput) {
                chatInput.placeholder = `Écrivez à ${this.dataset.model}...`;
            }
            
            // Effacer les messages précédents
            if (chatMessages) {
                chatMessages.innerHTML = '';
            }
            
            // Mettre à jour l'apparence des boutons
            updateModelButtonsAppearance();
            
            // Afficher le message de confirmation
            showModelSelectionMessage(this.dataset.model);
            
            // Activer le formulaire
            if (chatInput) chatInput.disabled = false;
            if (sendButton) sendButton.disabled = false;
        });
        
        // Gestion du survol
        button.addEventListener('mouseenter', function() {
            if (this.dataset.model !== localStorage.getItem('selectedModel')) {
                this.classList.remove('bg-custom-mid');
                this.classList.add('bg-custom-white');
                this.style.borderColor = '#000000';
            }
        });
        
        button.addEventListener('mouseleave', function() {
            if (this.dataset.model !== localStorage.getItem('selectedModel')) {
                this.classList.remove('bg-custom-white');
                this.classList.add('bg-custom-mid');
                this.style.borderColor = '';
            }
        });
    });

    // Fonction pour charger l'historique d'une conversation
    function loadConversationHistory(conversationId) {
        // Effacer les messages précédents
        if (chatMessages) {
            chatMessages.innerHTML = '';
        }
        
        // Afficher un message de chargement
        const loadingId = 'loading-history-' + Date.now();
        addLoadingMessage(loadingId);
        
        // Récupérer l'historique de la conversation
        fetch(`/api/conversation/${conversationId}`)
            .then(response => response.json())
            .then(data => {
                // Supprimer le message de chargement
                const loadingElement = document.getElementById(loadingId);
                if (loadingElement) {
                    loadingElement.remove();
                }
                
                if (data.success && data.messages) {
                    // Afficher chaque message de l'historique
                    data.messages.forEach(message => {
                        if (message.role === 'user') {
                            addUserMessage(message.content);
                        } else if (message.role === 'assistant') {
                            addAIMessage(message.content, false); // false = pas d'animation
                        }
                    });
                    
                    // Faire défiler vers le bas
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                } else {
                    addErrorMessage('Erreur lors du chargement de l\'historique');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                
                // Supprimer le message de chargement
                const loadingElement = document.getElementById(loadingId);
                if (loadingElement) {
                    loadingElement.remove();
                }
                
                addErrorMessage('Erreur lors du chargement de l\'historique');
            });
    }
    
    // Fonction pour ajouter un message utilisateur
    function addUserMessage(content) {
        if (!chatMessages) return;
        
        const template = document.getElementById('user-message-template');
        if (template) {
            const clone = document.importNode(template.content, true);
            
            clone.querySelector('.message-content').textContent = content;
            chatMessages.appendChild(clone);
            
            // Faire défiler vers le bas
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    }
    
    // Fonction pour ajouter un message AI
    function addAIMessage(content, animate = true) {
        if (!chatMessages) return;
        
        const template = document.getElementById('ai-message-template');
        if (template) {
            const clone = document.importNode(template.content, true);
            const messageContentElement = clone.querySelector('.message-content');
            
            // Vérifier si l'animation de typing est activée
            const typingAnimationEnabled = true; // Par défaut activé, on retirera le paramètre plus tard
            
            if (!typingAnimationEnabled || !animate) {
                // Si l'animation est désactivée, afficher le message directement
                messageContentElement.textContent = content;
                chatMessages.appendChild(clone);
                
                // Faire défiler vers le bas
                chatMessages.scrollTop = chatMessages.scrollHeight;
                return;
            }
            
            // Ajouter le message au DOM d'abord sans contenu
            messageContentElement.textContent = '';
            chatMessages.appendChild(clone);
            
            // Faire défiler vers le bas
            chatMessages.scrollTop = chatMessages.scrollHeight;
            
            // Récupérer l'élément après qu'il a été ajouté au DOM
            const addedMessage = chatMessages.lastElementChild.querySelector('.message-content');
            
            // Activer l'animation de typing
            addedMessage.classList.add('active');
            
            // Préserver le style white-space: pre-wrap pendant l'animation
            addedMessage.style.whiteSpace = 'pre-wrap';
            
            // Variable pour suivre si l'utilisateur a scrollé manuellement
            let userHasScrolled = false;
            
            // Gestionnaire d'événement pour détecter le scroll manuel
            const scrollHandler = function() {
                // Si l'utilisateur n'est pas tout en bas, marquer comme scrollé manuellement
                if (chatMessages.scrollTop + chatMessages.clientHeight < chatMessages.scrollHeight - 10) {
                    userHasScrolled = true;
                } else {
                    userHasScrolled = false;
                }
            };
            
            // Ajouter l'écouteur d'événement pour le scroll
            chatMessages.addEventListener('scroll', scrollHandler);
            
            // Simuler l'effet de typing caractère par caractère
            let currentText = '';
            let charIndex = 0;
            
            // Calculer la vitesse en fonction de la longueur du message
            // Plus le message est long, plus on va vite
            const baseSpeed = 3; // Vitesse de base plus rapide (était 10 avant)
            const punctuationDelay = 30; // Délai plus court pour la ponctuation (était 100 avant)
            
            function typeNextChar() {
                if (charIndex < content.length) {
                    // Ajouter le caractère suivant
                    currentText += content.charAt(charIndex);
                    addedMessage.textContent = currentText;
                    charIndex++;
                    
                    // Faire défiler vers le bas à chaque ajout de caractère
                    // chatMessages.scrollTop = chatMessages.scrollHeight;
                    
                    // Vitesse de typing variable selon le contenu
                    let delay = baseSpeed; // Vitesse par défaut plus rapide
                    
                    // Ralentir pour la ponctuation, mais moins qu'avant
                    const currentChar = content.charAt(charIndex - 1);
                    if (currentChar === '.' || currentChar === '?' || currentChar === '!') {
                        delay = punctuationDelay;
                    } 
                    // Ralentir légèrement pour les retours à la ligne
                    else if (currentChar === '\n') {
                        delay = 15;
                    }
                    
                    // Pour les messages longs, accélérer progressivement
                    if (content.length > 500 && charIndex > 100) {
                        delay = Math.max(1, delay / 2); // Minimum 1ms pour éviter les problèmes
                    }
                    
                    setTimeout(typeNextChar, delay);
                } else {
                    // Animation terminée, retirer les classes d'animation
                    addedMessage.classList.remove('active');
                    addedMessage.classList.remove('animate');
                    
                    // Supprimer l'écouteur d'événement de scroll une fois l'animation terminée
                    chatMessages.removeEventListener('scroll', scrollHandler);
                }
            }
            
            // Démarrer l'animation après un court délai
            setTimeout(() => {
                addedMessage.classList.add('animate');
                setTimeout(typeNextChar, 100); // Délai initial plus court (était 300 avant)
            }, 50); // Délai avant démarrage plus court (était 100 avant)
        }
    }
    
    // Fonction pour ajouter un message de chargement
    function addLoadingMessage(id) {
        if (!chatMessages) return;
        
        const template = document.getElementById('loading-message-template');
        if (template) {
            const clone = document.importNode(template.content, true);
            
            clone.querySelector('.loading-message').id = id;
            chatMessages.appendChild(clone);
            
            // Faire défiler vers le bas
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    }
    
    // Fonction pour ajouter un message d'erreur
    function addErrorMessage(errorText) {
        const chatMessagesElement = document.getElementById('chat-messages');
        if (!chatMessagesElement) {
            console.warn('Élément chat-messages non trouvé');
            return;
        }
        
        try {
            const template = document.getElementById('error-message-template');
            if (!template) {
                console.warn('Template error-message-template non trouvé');
                // Fallback si le template n'existe pas
                const errorDiv = document.createElement('div');
                errorDiv.className = 'flex justify-center mb-4';
                errorDiv.innerHTML = `<div class="bg-red-100 border border-red-400 text-red-700 rounded-lg py-2 px-4 max-w-[80%]">
                    <div class="whitespace-pre-wrap">${errorText}</div>
                </div>`;
                chatMessagesElement.appendChild(errorDiv);
                return;
            }
            
            const clone = document.importNode(template.content, true);
            
            const messageContent = clone.querySelector('.message-content');
            if (messageContent) {
                messageContent.textContent = errorText;
            }
            
            chatMessagesElement.appendChild(clone);
            
            // Faire défiler vers le bas
            chatMessagesElement.scrollTop = chatMessagesElement.scrollHeight;
        } catch (error) {
            console.error('Erreur lors de l\'affichage du message d\'erreur:', error);
            // Ne pas propager l'erreur pour éviter une boucle infinie
        }
    }
    
    // Gestion du bouton d'envoi
    sendButton.addEventListener('mouseenter', function() {
        // scaler le bouton
        this.style.transform = 'scale(1.1)';
    });
    
    sendButton.addEventListener('mouseleave', function() {
        this.style.transform = 'scale(1)';
    });
    
    // Sélectionner un modèle par défaut si aucun n'est sélectionné
    if (!localStorage.getItem('selectedModel') && modelButtons.length > 0) {
        // Sélectionner le premier modèle par défaut
        const defaultModel = modelButtons[0].dataset.model;
        localStorage.setItem('selectedModel', defaultModel);
    }
    
    // Initialiser l'apparence des boutons au chargement de la page
    updateModelButtonsAppearance();

    // Gestion du textarea pour les retours à la ligne
    if (chatInput) {
        // Ajuster automatiquement la hauteur du textarea en fonction du contenu
        function adjustTextareaHeight() {
            chatInput.style.height = 'auto';
            chatInput.style.height = Math.min(chatInput.scrollHeight, 200) + 'px';
        }
        
        // Appliquer l'ajustement lors de la saisie
        chatInput.addEventListener('input', adjustTextareaHeight);
        
        // Gérer l'envoi avec Entrée (mais permettre Shift+Entrée pour les retours à la ligne)
        chatInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (this.value.trim() !== '') {
                    chatForm.dispatchEvent(new Event('submit'));
                }
            }
        });
        
        // Initialiser la hauteur au chargement
        setTimeout(adjustTextareaHeight, 0);
    }
    
    // Envoi du message
    if (chatForm) {
        chatForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const selectedModel = localStorage.getItem('selectedModel');
            
            if (!selectedModel || !chatInput.value.trim()) return;
            
            // Récupérer le message et le vider
            const message = chatInput.value.trim();
            chatInput.value = '';
            adjustTextareaHeight();
            
            // Désactiver le formulaire pendant l'envoi
            chatInput.disabled = true;
            sendButton.disabled = true;
            
            // Ajouter le message de l'utilisateur
            addUserMessage(message);
            
            // Ajouter un message de chargement
            const loadingMessageId = 'loading-' + Date.now();
            addLoadingMessage(loadingMessageId);
            
            // Envoyer la requête au backend Laravel (pas directement à Ollama)
            fetch('/api/chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    message: message,
                    model: selectedModel,
                    conversation_id: currentConversationId ? currentConversationId : null,
                    temperature: parseFloat(temperatureSlider ? temperatureSlider.value : 0.7),
                    max_tokens: parseInt(maxTokensSlider ? maxTokensSlider.value : 1024)
                })
            })
            .then(async response => {
                // Vérifier si la réponse est OK avant de parser le JSON
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error(`Erreur serveur (${response.status}): ${text}`);
                    });
                }
                return response.json();
            })
            .then(data => {
                // Supprimer le message de chargement
                const loadingElement = document.getElementById(loadingMessageId);
                if (loadingElement) {
                    loadingElement.remove();
                }
                
                if (data.success) {
                    // Ajouter la réponse de l'IA
                    addAIMessage(data.response);
                    
                    // Mettre à jour l'ID de conversation si c'est une nouvelle conversation
                    if (data.conversation_id && !currentConversationId) {
                        currentConversationId = data.conversation_id;
                        
                        // Au lieu de recharger la page, ajouter dynamiquement la conversation à la liste
                        const historySection = document.getElementById('history-section');
                        if (historySection) {
                            // Créer un nouvel élément de conversation
                            const newConversationItem = document.createElement('div');
                            newConversationItem.className = 'conversation-item px-3 py-2 rounded-md hover:bg-custom-light-dark-mode cursor-pointer transition-colors duration-200 bg-custom-light-dark-mode';
                            newConversationItem.dataset.conversationId = data.conversation_id;
                            newConversationItem.dataset.model = localStorage.getItem('selectedModel');
                            
                            // Titre de la conversation (utiliser le début du message)
                            const title = message.length > 30 ? message.substring(0, 30) + '...' : message;
                            
                            // Contenu de l'élément de conversation
                            newConversationItem.innerHTML = `
                                <div class="text-sm text-custom-black dark:text-custom-white font-medium truncate">
                                    ${title}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    ${localStorage.getItem('selectedModel')}
                                </div>
                            `;
                            
                            // Ajouter l'événement de clic
                            newConversationItem.addEventListener('click', function() {
                                // Récupérer l'ID de la conversation et le modèle
                                const conversationId = this.dataset.conversationId;
                                const modelName = this.dataset.model;
                                
                                // Mettre à jour l'apparence des éléments de conversation
                                document.querySelectorAll('.conversation-item').forEach(item => {
                                    item.classList.remove('bg-custom-light-dark-mode');
                                });
                                this.classList.add('bg-custom-light-dark-mode');
                                
                                // Mettre à jour le modèle sélectionné
                                if (modelName) {
                                    localStorage.setItem('selectedModel', modelName);
                                    updateModelButtonsAppearance();
                                }
                                
                                // Charger l'historique de la conversation
                                loadConversationHistory(conversationId);
                                
                                // Mettre à jour l'ID de conversation courante
                                currentConversationId = conversationId;
                            });
                            
                            // Insérer le nouvel élément au début de la liste
                            // const emptyMessage = historySection.querySelector('.text-gray-500');
                            // if (emptyMessage) {
                            //     // Remplacer le message "Aucune conversation enregistrée"
                            //     historySection.removeChild(emptyMessage);
                            // }
                            // /Bug : la méthode choisie (removeChild) suppose que le nœud est enfant direct ; quand ce n’est pas le cas, d’où l’exception.
                            
                            // Insérer après le bouton "Nouvelle conversation" s'il existe
                            const newConversationButton = historySection.querySelector('.flex.items-center');
                            if (newConversationButton) {
                                historySection.insertBefore(newConversationItem, newConversationButton.nextSibling);
                            } else {
                                historySection.insertBefore(newConversationItem, historySection.firstChild);
                            }
                        }
                    }
                    
                    // Si c'est une nouvelle conversation, mettre à jour l'historique
                    if (data.is_new_conversation) {
                        updateConversationHistory();
                    }
                } else {
                    // Afficher le message d'erreur
                    addErrorMessage(data.error || 'Une erreur est survenue');
                }
            })
            .catch(error => {
                // Gérer les erreurs
                console.error('Error:', error);
                
                // Supprimer le message de chargement s'il existe encore
                const loadingElement = document.getElementById(loadingMessageId);
                if (loadingElement) {
                    loadingElement.remove();
                }
                
                // Afficher un message d'erreur plus détaillé
                addErrorMessage(error.message || 'Erreur de communication avec le serveur');
                
                // Vérifier si la conversation a été créée malgré l'erreur
                if (currentConversationId) {
                    console.log('Une conversation existe déjà (ID: ' + currentConversationId + '), mise à jour de l\'historique');
                    updateConversationHistory();
                }
            })
            .finally(() => {
                // Réactiver le formulaire
                const chatInputElement = document.getElementById('chat-input');
                const sendButtonElement = document.getElementById('send-button');
                
                if (chatInputElement) chatInputElement.disabled = false;
                if (sendButtonElement) sendButtonElement.disabled = false;
            });
        });
    }
    
    // Fonction pour mettre à jour l'historique des conversations
    function updateConversationHistory() {
        // Récupérer l'historique des conversations via une requête AJAX
        fetch('/api/conversations')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Récupérer la section d'historique
                    const historySection = document.getElementById('history-section');
                    if (!historySection) return;
                    
                    // Vider l'historique actuel
                    historySection.innerHTML = '';
                    
                    // Si aucune conversation, afficher un message
                    if (data.conversations.length === 0) {
                        const emptyMessage = document.createElement('div');
                        emptyMessage.className = 'text-sm text-gray-500 dark:text-gray-300 px-3 py-2';
                        emptyMessage.textContent = 'Aucune conversation enregistrée';
                        historySection.appendChild(emptyMessage);
                        return;
                    }
                    
                    // Ajouter chaque conversation à l'historique
                    data.conversations.forEach(conversation => {
                        const conversationItem = document.createElement('div');
                        conversationItem.className = 'conversation-item px-3 py-2 rounded-md hover:bg-custom-light-dark-mode cursor-pointer transition-colors duration-200';
                        conversationItem.dataset.conversationId = conversation.id;
                        conversationItem.dataset.model = conversation.model_name;
                        
                        // Ajouter la classe active si c'est la conversation courante
                        if (conversation.id == currentConversationId) {
                            conversationItem.classList.add('bg-custom-light-dark-mode');
                        }
                        
                        conversationItem.innerHTML = `
                            <div class="text-sm text-custom-black dark:text-custom-white font-medium truncate">
                                ${conversation.title || 'Conversation du ' + new Date(conversation.created_at).toLocaleDateString()}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                ${conversation.model_name}
                            </div>
                        `;
                        
                        // Ajouter l'événement de clic
                        conversationItem.addEventListener('click', function() {
                            // Récupérer l'ID de la conversation et le modèle
                            const conversationId = this.dataset.conversationId;
                            const modelName = this.dataset.model;
                            
                            // Mettre à jour l'apparence des éléments de conversation
                            document.querySelectorAll('.conversation-item').forEach(item => {
                                item.classList.remove('bg-custom-light-dark-mode');
                            });
                            this.classList.add('bg-custom-light-dark-mode');
                            
                            // Mettre à jour le modèle sélectionné
                            if (modelName) {
                                localStorage.setItem('selectedModel', modelName);
                                updateModelButtonsAppearance();
                            }
                            
                            // Charger l'historique de la conversation
                            loadConversationHistory(conversationId);
                            
                            // Mettre à jour l'ID de conversation courante
                            currentConversationId = conversationId;
                        });
                        
                        // Ajouter l'élément à la section d'historique
                        historySection.appendChild(conversationItem);
                    });
                }
            })
            .catch(error => {
                console.error('Erreur lors de la récupération de l\'historique des conversations:', error);
            });
    }
});

// recharger la page au changement de thème systeme
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    location.reload();
});
