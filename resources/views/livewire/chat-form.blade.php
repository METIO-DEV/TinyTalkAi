<div class="chat-form">
    <div class="flex h-full w-full items-center gap-2">
        <form
            x-data="{
                addUserMessage() {
                    if (!$wire.message.trim()) return;
                    if ($wire.isSummarizing || $wire.isLoading) return;
                    $wire.sendMessage();
                },
                autoResize(el) {
                    el.style.height = 'auto';
                    el.style.height = `${Math.min(el.scrollHeight, 175)}px`;
                    el.style.overflowY = el.scrollHeight > 175 ? 'auto' : 'hidden';
                }
            }"
            x-on:submit.prevent="addUserMessage()"
            class="flex min-w-0 flex-1 items-stretch gap-2"
        >
            <x-ui.textarea
                wire:model="message"
                x-init="autoResize($el)"
                x-on:input="autoResize($event.target)"
                x-on:keydown.enter="$event.shiftKey ? null : $event.preventDefault()"
                x-on:keydown.enter.stop="if (!$event.shiftKey) { addUserMessage(); }"
                class="min-h-10 max-h-[175px] resize-none rounded-r-none text-sm lg:text-base"
                placeholder="{{ $selectedModel ? __('Écrivez à') . ' ' . $selectedModel . '... ' . __('Shift+Enter pour un retour à la ligne') : __('Sélectionnez un modèle...') }}"
                rows="1"
                @disabled(! $selectedModel || $isSummarizing)
                wire:loading.attr="disabled"
                wire:target="sendMessage"
            />

            <x-ui.button
                type="submit"
                class="h-auto rounded-l-none px-4 sm:px-5"
                @disabled(! $selectedModel || $isSummarizing)
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
                <svg wire:loading wire:target="sendMessage" class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </x-ui.button>
        </form>

        @livewire('document-uploader', ['buttonClass' => 'hidden lg:inline-flex'], key('document-uploader'))

        <div class="lg:hidden relative" x-data="{ open: false }" @click.outside="open = false">
            <x-ui.button
                type="button"
                variant="outline"
                size="icon"
                @click="open = !open"
                x-bind:aria-expanded="open.toString()"
                aria-label="{{ __('Options') }}"
                title="{{ __('Options') }}"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
            </x-ui.button>

            <x-ui.card
                x-show="open"
                x-transition
                padding="sm"
                class="absolute bottom-full right-0 z-20 mb-2 flex items-center gap-2 shadow-lg"
                style="display: none;"
            >
                <x-ui.button
                    type="button"
                    variant="outline"
                    size="icon"
                    wire:click="$dispatch('openDocumentUploader')"
                    title="{{ __('Ajouter un document') }}"
                    aria-label="{{ __('Ajouter un document') }}"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </x-ui.button>
                <livewire:rag-toggle />
            </x-ui.card>
        </div>
    </div>
</div>
