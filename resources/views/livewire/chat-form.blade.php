<div class="chat-form">
    <form wire:submit.prevent="sendMessage" 
          x-data="{ 
              addUserMessage() {
                  if ($wire.message.trim() !== '') {
                      // Clone le template du message utilisateur
                      const template = document.getElementById('user-message-template');
                      const messageDiv = template.content.cloneNode(true);
                      
                      // Ajoute le contenu du message
                      messageDiv.querySelector('.message-content').textContent = $wire.message;
                      
                      // Ajoute le message au conteneur
                      document.getElementById('chat-messages').appendChild(messageDiv);
                      
                      // Scroll vers le bas
                      document.getElementById('chat-messages').scrollTop = document.getElementById('chat-messages').scrollHeight;
                  }
              }
          }"
          x-on:submit="addUserMessage()"
          class="flex gap-4 w-full justify-between items-stretch">
        <textarea 
            wire:model="message" 
            x-data="{}"
            x-on:keydown.enter.prevent="$event.shiftKey || $wire.sendMessage()"
            class="flex-1 border border-custom-mid rounded-l-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-custom-mid bg-custom-white text-custom-black dark:text-custom-white dark:border-custom-white dark:bg-custom-light-dark-mode resize-none overflow-y-auto [&::-webkit-scrollbar]:w-0" 
            placeholder="{{ $selectedModel ? 'Écrivez à '.$selectedModel.'...' : 'Sélectionnez un modèle...' }}"
            rows="1"
            {{ $selectedModel ? '' : 'disabled' }}
        ></textarea>
        <button 
            type="submit" 
            class="bg-custom-black border border-custom-black text-white dark:text-custom-white dark:border-custom-white dark:bg-custom-light-dark-mode px-6 items-center rounded-r-lg transition-all duration-200 flex justify-center"
            {{ $selectedModel ? '' : 'disabled' }}
            wire:loading.attr="disabled"
        >
            <span wire:loading.remove>Envoyer</span>
            <svg wire:loading class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </button>
    </form>
</div>