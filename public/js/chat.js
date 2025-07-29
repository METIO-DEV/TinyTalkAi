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
