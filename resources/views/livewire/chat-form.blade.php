<div class="chat-form">
    <div class="w-full h-full flex flex-row gap-2 justify-between items-center">
        <form 
              x-data="{ 
                  addUserMessage() {
                      if (!$wire.message.trim()) return;
                      if ($wire.isSummarizing || $wire.isLoading) return;
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
              x-on:submit.prevent="addUserMessage()"
              class="flex gap-2 w-full justify-between items-stretch">
            <!-- Mobile textarea (before lg): minimal placeholder -->
            <textarea 
                wire:model="message" 
                x-data="{}"
                x-init="autoResize($el)"
                x-on:input="autoResize($event.target)"
                x-on:keydown.enter="$event.shiftKey ? null : $event.preventDefault()"
                x-on:keydown.enter.stop="if (!$event.shiftKey) { addUserMessage(); }"
                class="flex-1 border border-custom-mid rounded-l-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-custom-mid bg-custom-white text-custom-black dark:text-custom-white dark:border-custom-white dark:bg-custom-light-dark-mode resize-none min-h-[35px] max-h-[250px] overflow-auto xl:text-lg text-xs lg:hidden" 
                placeholder="{{ $selectedModel ? __('Écrivez à') . ' ' . $selectedModel . '.' : __('Sélectionnez un modèle...') }}"
                rows="1"
                {{ $selectedModel ? '' : 'disabled' }}
                {{ $isSummarizing ? 'disabled' : '' }}
                wire:loading.attr="disabled"
                wire:target="sendMessage"
            ></textarea>

            <!-- Desktop textarea (lg and above): full placeholder with hint -->
            <textarea 
                wire:model="message" 
                x-data="{}"
                x-init="autoResize($el)"
                x-on:input="autoResize($event.target)"
                x-on:keydown.enter="$event.shiftKey ? null : $event.preventDefault()"
                x-on:keydown.enter.stop="if (!$event.shiftKey) { addUserMessage(); }"
                class="hidden lg:block flex-1 border border-custom-mid rounded-l-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-custom-mid bg-custom-white text-custom-black dark:text-custom-white dark:border-custom-white dark:bg-custom-light-dark-mode resize-none min-h-[40px] max-h-[250px] overflow-auto xl:text-lg text-xs" 
                placeholder="{{ $selectedModel ? __('Écrivez à') . ' ' . $selectedModel . '... ' . __('Shift+Enter pour un retour à la ligne') : __('Sélectionnez un modèle...') }}"
                rows="1"
                {{ $selectedModel ? '' : 'disabled' }}
                {{ $isSummarizing ? 'disabled' : '' }}
                wire:loading.attr="disabled"
                wire:target="sendMessage"
            ></textarea>
            <button 
                type="submit" 
                class="bg-custom-black border border-custom-black text-white dark:text-custom-white dark:border-custom-white dark:bg-custom-light-dark-mode hover:dark:bg-custom-mid hover:dark:text-custom-black hover:bg-custom-mid px-6 items-center rounded-r-lg transition-all duration-200 flex justify-center"
                {{ $selectedModel ? '' : 'disabled' }}
                {{ $isSummarizing ? 'disabled' : '' }}
                wire:loading.attr="disabled"
                wire:loading.class="opacity-50 cursor-not-allowed"
                wire:target="sendMessage"
                title="{{ $selectedModel ? __('Envoyer') : __('Sélectionnez un modèle') }}"
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
        <div class="h-full lg:flex hidden">
            @livewire('document-uploader')
        </div>
            
        <!-- Options (mobile) discrètes : icône seule, menu qui s'ouvre vers le haut -->
        <div class="lg:hidden relative" x-data="{ open: false }" @click.outside="open = false">
            <button
                type="button"
                @click="open = !open"
                :aria-expanded="open.toString()"
                aria-label="{{ __('Options') }}"
                class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-custom-black bg-custom-black text-white hover:bg-custom-mid hover:text-custom-black transition-colors dark:bg-custom-light-dark-mode dark:text-custom-white dark:border-custom-white hover:dark:bg-custom-mid hover:dark:text-custom-black"
                title="{{ __('Options') }}"
            >
                <!-- Icône réglages (sliders) -->
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="21" y1="4" x2="14" y2="4"></line>
                    <line x1="10" y1="4" x2="3" y2="4"></line>
                    <line x1="21" y1="12" x2="12" y2="12"></line>
                    <line x1="8" y1="12" x2="3" y2="12"></line>
                    <line x1="21" y1="20" x2="16" y2="20"></line>
                    <line x1="12" y1="20" x2="3" y2="20"></line>
                    <circle cx="12" cy="4" r="2"></circle>
                    <circle cx="8" cy="12" r="2"></circle>
                    <circle cx="16" cy="20" r="2"></circle>
                </svg>
            </button>

            <!-- Menu déroulant vers le haut -->
            <div
                x-show="open"
                x-transition
                class="absolute bottom-full right-0 mb-2 z-20 bg-custom-white border border-custom-mid rounded-lg shadow-lg p-2 flex items-stretch gap-2 dark:bg-custom-light-dark-mode dark:border-custom-white"
            >
                <div class="h-full">
                    @livewire('document-uploader')
                </div>
                <div class="flex items-center">
                    <livewire:rag-toggle />
                </div>
            </div>
        </div>
    </div>
</div>