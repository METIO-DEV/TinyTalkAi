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
        if (template) {
            const clone = document.importNode(template.content, true);
            
            // Définir le nom du modèle
            clone.querySelector('.model-name').textContent = selectedModel;
            
            // Insérer au début du conteneur de messages
            if (chatMessages.firstChild) {
                chatMessages.insertBefore(clone, chatMessages.firstChild);
            } else {
                chatMessages.appendChild(clone);
            }
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
            updateModelButtonsAppearance();
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
            
            const userMessage = chatInput.value.trim();
            
            // Ajouter le message de l'utilisateur
            addUserMessage(userMessage);
            
            // Vider l'input et désactiver le formulaire pendant l'envoi
            chatInput.value = '';
            chatInput.disabled = true;
            sendButton.disabled = true;
            
            // Ajouter un message de chargement
            const loadingMessageId = 'loading-' + Date.now();
            addLoadingMessage(loadingMessageId);
            
            // Envoyer la requête au serveur
            fetch('/api/chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    message: userMessage,
                    model: selectedModel,
                    temperature: parseFloat(temperatureSlider ? temperatureSlider.value : 0.7),
                    max_tokens: parseInt(maxTokensSlider ? maxTokensSlider.value : 1024)
                })
            })
            .then(response => response.json())
            .then(data => {
                // Supprimer le message de chargement
                const loadingElement = document.getElementById(loadingMessageId);
                if (loadingElement) {
                    loadingElement.remove();
                }
                
                if (data.success) {
                    // Ajouter la réponse du modèle
                    addAIMessage(data.response);
                } else {
                    // Afficher l'erreur
                    addErrorMessage(data.error || 'Une erreur est survenue');
                }
            })
            .catch(error => {
                // Supprimer le message de chargement
                const loadingElement = document.getElementById(loadingMessageId);
                if (loadingElement) {
                    loadingElement.remove();
                }
                
                // Afficher l'erreur
                addErrorMessage('Erreur de communication avec le serveur');
                console.error('Error:', error);
            })
            .finally(() => {
                // Réactiver le formulaire
                chatInput.disabled = false;
                sendButton.disabled = false;
            });
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
    function addAIMessage(content) {
        if (!chatMessages) return;
        
        const template = document.getElementById('ai-message-template');
        if (template) {
            const clone = document.importNode(template.content, true);
            const messageContentElement = clone.querySelector('.message-content');
            
            // Vérifier si l'animation de typing est activée
            const typingAnimationEnabled = true; // Par défaut activé, on retirera le paramètre plus tard
            
            if (!typingAnimationEnabled) {
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
        if (!chatMessages) return;
        
        const template = document.getElementById('error-message-template');
        if (template) {
            const clone = document.importNode(template.content, true);
            
            clone.querySelector('.message-content').textContent = errorText;
            chatMessages.appendChild(clone);
            
            // Faire défiler vers le bas
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    }
});

// recharger la page au changement de thème systeme
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    location.reload();
});
