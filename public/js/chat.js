/**
 * TinyTalk AI - Script principal pour l'interface de chat
 * 
 * Ce script gère les interactions minimales de l'interface de chat :
 * - Sélection des modèles
 * - Ajustement des paramètres (température, max_tokens)
 * - Gestion des sections pliables dans la sidebar
 */
document.addEventListener('DOMContentLoaded', function() {
    // Variables
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
            
            if (icon.style.transform === 'rotate(-90deg)') {
                icon.style.transform = 'rotate(0deg)';
            } else {
                icon.style.transform = 'rotate(-90deg)';
            }
            
            if (targetElement.classList.contains('hidden')) {
                // Animation d'ouverture
                targetElement.classList.remove('hidden');
                targetElement.style.maxHeight = '0';
                targetElement.style.overflow = 'hidden';
                targetElement.style.opacity = '0';
                
                // Force un reflow
                targetElement.offsetHeight;
                
                // Anime l'ouverture
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
                targetElement.offsetHeight;
                
                // Anime la fermeture
                targetElement.style.transition = 'max-height 0.3s ease-in-out, opacity 0.3s ease-in-out';
                targetElement.style.maxHeight = '0';
                targetElement.style.opacity = '0';
                
                // Cache l'élément après l'animation
                setTimeout(() => {
                    targetElement.classList.add('hidden');
                    targetElement.style.overflow = '';
                    targetElement.style.maxHeight = '';
                    targetElement.style.opacity = '';
                }, 300);
            }
        });
    });
    
    // Fonction pour mettre à jour l'apparence des boutons de modèle
    function updateModelButtonsAppearance() {
        const selectedModel = localStorage.getItem('selectedModel');
        
        modelButtons.forEach(button => {
            // Réinitialiser tous les boutons
            button.classList.remove('bg-custom-light-dark-mode');
            button.classList.remove('bg-custom-white');
            button.classList.add('bg-custom-mid');
            button.style.borderColor = '';
            
            // Mettre en évidence le modèle sélectionné
            if (button.dataset.model === selectedModel) {
                button.classList.remove('bg-custom-mid');
                button.classList.add('bg-custom-light-dark-mode');
                
                // Mettre à jour le placeholder du textarea
                if (chatInput) {
                    chatInput.placeholder = `Écrivez à ${selectedModel}...`;
                }
                
                // Activer le formulaire
                if (chatInput) chatInput.disabled = false;
            }
        });
    }
    
    // Gestion des boutons de modèle
    modelButtons.forEach(button => {
        // Gestion du clic
        button.addEventListener('click', function() {
            localStorage.setItem('selectedModel', this.dataset.model);
            
            // Mettre à jour l'apparence des boutons
            updateModelButtonsAppearance();
        });
        
        // Effet de survol
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
        
        // Initialiser la hauteur au chargement
        setTimeout(adjustTextareaHeight, 0);
    }
});

// recharger la page au changement de thème systeme
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    location.reload();
});
