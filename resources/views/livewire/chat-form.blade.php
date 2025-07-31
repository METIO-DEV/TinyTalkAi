<div class="chat-form">
    <div class="w-full h-full flex flex-row gap-2 justify-between items-center">
        <form 
              x-data="{ 
                  addUserMessage() {
                      if (!$wire.message.trim()) return;
                      $wire.sendMessage();
                  },
                  autoResize(el) {
                      el.style.height = 'auto';
                      el.style.height = (el.scrollHeight) + 'px';
                      const maxHeight = 175;
                      if (el.scrollHeight > maxHeight) {
                          el.style.height = maxHeight + 'px';
                          el.style.overflowY = 'auto';
                      } else {
                          el.style.overflowY = 'hidden';
                      }
                  }
              }"
              x-on:submit="addUserMessage()"
              class="flex gap-2 w-full justify-between items-stretch">
            <textarea 
                wire:model="message" 
                x-data="{}"
                x-init="autoResize($el)"
                x-on:input="autoResize($event.target)"
                x-on:keydown.enter="$event.shiftKey ? null : $event.preventDefault()"
                x-on:keydown.enter.stop="if (!$event.shiftKey) { addUserMessage(); }"
                class="flex-1 border border-custom-mid rounded-l-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-custom-mid bg-custom-white text-custom-black dark:text-custom-white dark:border-custom-white dark:bg-custom-light-dark-mode resize-none min-h-[40px] max-h-[250px] overflow-y-auto" 
                placeholder="{{ $selectedModel ? 'Écrivez à '.$selectedModel.'... (Shift+Enter pour un retour à la ligne)' : 'Sélectionnez un modèle...' }}"
                rows="1"
                {{ $selectedModel ? '' : 'disabled' }}
            ></textarea>        
            <button 
                type="submit" 
                class="bg-custom-black border border-custom-black text-white dark:text-custom-white dark:border-custom-white dark:bg-custom-light-dark-mode hover:dark:bg-custom-mid hover:dark:text-custom-black hover:bg-custom-mid px-6 items-center rounded-r-lg transition-all duration-200 flex justify-center"
                {{ $selectedModel ? '' : 'disabled' }}
                {{ $isSummarizing ? 'disabled' : '' }}
                wire:loading.attr="disabled"
                wire:loading.class="opacity-50 cursor-not-allowed"
                wire:target="sendMessage, $parent"
            >
                <span wire:loading.remove wire:target="sendMessage">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                    </svg>
                </span>
                <svg wire:loading wire:target="sendMessage" class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </button>
        </form>
        <!-- Bouton d'upload de document (déplacé en dehors du formulaire) -->
        <div class="h-full">
            @livewire('document-uploader')
        </div>
    </div>
</div>