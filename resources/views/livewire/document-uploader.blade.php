<div class="flex items-center justify-between h-full">
    <!-- Bouton pour ouvrir le modal -->
    <button 
        type="button"
        wire:click="$dispatch('openDocumentUploader')"
        class="bg-custom-white border border-custom-mid text-custom-black hover:bg-custom-mid dark:bg-custom-light-dark-mode dark:border-custom-white dark:text-custom-white hover:dark:bg-custom-mid hover:dark:text-custom-black px-3 py-2 h-full rounded-lg transition-all duration-200 flex items-center"
        title="{{ __('Ajouter un document') }}"
    >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
    </button>

    <!-- Modal d'upload -->
    <div 
        x-data="{ 
            show: @entangle('isOpen'),
            closeModal() {
                this.show = false;
                $wire.closeModal();
            }
        }"
        x-show="show"
        x-on:closeModal.window="closeModal()"
        x-init="$watch('show', value => { if (!value) $wire.closeModal(); })"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 overflow-y-auto"
        style="display: none;"
    >
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" x-on:click="closeModal()"></div>
            
            <div class="bg-custom-white dark:bg-custom-light-dark-mode rounded-lg overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full z-10">
                <div class="px-6 py-4">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-custom-black dark:text-custom-white">
                            {{ __('Ajouter un document') }}
                        </h3>
                        <button type="button" 
                                x-on:click="closeModal()"
                                class="text-gray-500 hover:text-gray-700">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    
                    <form wire:submit.prevent="uploadDocument">
                        <!-- Titre du document -->
                        <div class="mb-4">
                            <label for="title" class="block text-sm font-medium text-custom-black dark:text-custom-white mb-1">
                                {{ __('Titre du document') }}
                            </label>
                            <input 
                                type="text" 
                                id="title" 
                                wire:model="title" 
                                class="w-full border border-custom-mid rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-custom-mid bg-custom-white text-custom-black dark:text-custom-white dark:border-custom-white dark:bg-custom-light-dark-mode"
                                placeholder="{{ __('Entrez un titre pour le document') }}"
                                required
                            >
                            @error('title') 
                                <span class="text-red-500 text-xs mt-1">{{ $message }}</span> 
                            @enderror
                        </div>
                        
                        <!-- Upload de fichier -->
                        <div class="mb-4">
                            <label for="document" class="block text-sm font-medium text-custom-black dark:text-custom-white mb-1">
                                {{ __('Fichier (.txt, .pdf, .docx uniquement, max 10MB)') }}
                            </label>
                            <input 
                                type="file" 
                                id="document" 
                                wire:model="document" 
                                accept=".txt,.pdf,.docx"
                                class="w-full border border-custom-mid rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-custom-mid bg-custom-white text-custom-black dark:text-custom-white dark:border-custom-white dark:bg-custom-light-dark-mode"
                                required
                            >
                            <div wire:loading wire:target="document">
                                <span class="text-sm text-gray-500">{{ __('Chargement...') }}</span>
                            </div>
                            @error('document') 
                                <span class="text-red-500 text-xs mt-1">{{ $message }}</span> 
                            @enderror
                        </div>
                        
                        <!-- Message de statut -->
                        @if($statusMessage)
                            <div class="mb-4 p-2 rounded-lg {{ str_contains(strtolower($statusMessage), 'erreur') ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">
                                {{ $statusMessage }}
                            </div>
                        @endif
                        
                        <!-- Boutons d'action -->
                        <div class="flex justify-end space-x-2">
                            <button 
                                type="button" 
                                x-on:click="closeModal()"
                                class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors"
                                wire:loading.attr="disabled"
                                wire:target="uploadDocument"
                            >
                                {{ __('Annuler') }}
                            </button>
                            <button 
                                type="submit" 
                                class="px-4 py-2 bg-custom-black text-white rounded-lg hover:bg-custom-mid hover:text-custom-black transition-colors"
                                wire:loading.attr="disabled"
                                wire:target="uploadDocument"
                            >
                                <span wire:loading.remove wire:target="uploadDocument">{{ __('Télécharger') }}</span>
                                <span wire:loading wire:target="uploadDocument">
                                    <svg class="animate-spin h-5 w-5 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    {{ __('Traitement...') }}
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
