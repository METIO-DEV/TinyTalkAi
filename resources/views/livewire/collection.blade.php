<div class="flex flex-col h-full">
    <!-- En-tête du composant avec titre et bouton d'ajout -->
    <div class="flex flex-row justify-between items-start mb-4">
        
        <!-- Bouton pour ouvrir le modal de création -->
        <button 
            type="button"
            wire:click="openCreateModal"
            class="bg-custom-white text-custom-black hover:bg-custom-mid dark:bg-custom-light-dark-mode  dark:text-custom-white hover:dark:bg-custom-mid hover:dark:text-custom-black px-2 py-1 rounded-lg text-sm transition-all duration-200 flex items-center"
            title="{{ __('Ajouter une collection') }}"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            <span class="ml-2">{{ __('Ajouter') }}</span>
        </button>

        <!-- Indicateur de chargement -->
        <div wire:loading wire:target="loadCollections, selectCollection, deleteCollection" class="flex justify-center">
            <svg class="animate-spin h-6 w-6 text-custom-black dark:text-custom-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>
    </div>
    
    <!-- Liste des collections -->
    <div class="flex-grow overflow-y-auto">
        @if(count($collections) > 0)
            <ul class="space-y-2">
                @foreach($collections as $collection)
                    <li>
                        <div class="flex items-center justify-between p-1 rounded-lg {{ $selectedCollection === $collection ? 'bg-custom-mid text-custom-black dark:bg-custom-mid dark:text-custom-black' : 'bg-custom-white dark:bg-custom-light-dark-mode text-custom-black dark:text-custom-white hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                            <button 
                                wire:click="selectCollection('{{ $collection }}')"
                                class="flex-grow text-left px-2 py-1 rounded-md"
                            >
                                <div class="flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                    </svg>
                                    <span>{{ $collection }}</span>
                                </div>
                            </button>
                            <!-- Actions -->
                        <div class="flex items-center space-x-1">
                            <!-- Bouton d'upload de documents -->
                            <button
                                wire:click="openUploadModal('{{ $collection }}')"
                                class="p-1 text-custom-black dark:text-custom-white hover:text-custom-mid dark:hover:text-custom-mid transition-all duration-200"
                                title="{{ __('Ajouter un document à cette collection') }}"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                </svg>
                            </button>
                            
                            <!-- Bouton de suppression -->
                            <button
                                wire:click="deleteCollection('{{ $collection }}')"
                                class="p-1 text-custom-black dark:text-custom-white hover:text-red-500 dark:hover:text-red-500 transition-all duration-200"
                                title="{{ __('Supprimer cette collection') }}"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="flex flex-col items-center justify-center h-32 text-gray-500 dark:text-gray-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                </svg>
                <p>{{ __('Aucune collection disponible') }}</p>
                <button 
                    wire:click="openCreateModal"
                    class="mt-2 text-sm text-custom-black dark:text-custom-white hover:underline"
                >
                    {{ __('Créer une collection') }}
                </button>
            </div>
        @endif
        
        
    </div>
    
    <!-- Modal de création de collection -->
    <div 
        x-data="{ 
            show: @entangle('isCreateModalOpen'),
            closeModal() {
                this.show = false;
                $wire.closeCreateModal();
            }
        }"
        x-show="show"
        x-on:closeModalAfterDelay.window="setTimeout(() => { closeModal() }, 1000)"
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
                            {{ __('Créer une nouvelle collection') }}
                        </h3>
                        <button type="button" 
                                x-on:click="closeModal()"
                                class="text-gray-500 hover:text-gray-700">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    
                    <form wire:submit.prevent="createCollection">
                        <!-- Nom de la collection -->
                        <div class="mb-4">
                            <label for="collectionName" class="block text-sm font-medium text-custom-black dark:text-custom-white mb-1">
                                {{ __('Nom de la collection') }}
                            </label>
                            <input 
                                type="text" 
                                id="collectionName" 
                                wire:model="collectionName" 
                                class="w-full border border-custom-mid rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-custom-mid bg-custom-white text-custom-black dark:text-custom-white dark:border-custom-white dark:bg-custom-light-dark-mode"
                                placeholder="{{ __('ma_collection') }}"
                                required
                            >
                            <p class="text-xs text-gray-500 mt-1">
                                {{ __('Lettres minuscules, chiffres et underscores uniquement (ex: ma_collection_1)') }}
                            </p>
                            @error('collectionName') 
                                <span class="text-red-500 text-xs mt-1">{{ $message }}</span> 
                            @enderror
                        </div>
                        
                        <!-- Message de statut -->
                        @if($statusMessage && $isCreateModalOpen)
                            <div class="mb-4 p-2 rounded-lg {{ $success ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
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
                                wire:target="createCollection"
                            >
                                {{ __('Annuler') }}
                            </button>
                            <button 
                                type="submit" 
                                class="px-4 py-2 bg-custom-black text-white rounded-lg hover:bg-custom-mid hover:text-custom-black transition-colors"
                                wire:loading.attr="disabled"
                                wire:target="createCollection"
                            >
                                <span wire:loading.remove wire:target="createCollection">{{ __('Créer') }}</span>
                                <span wire:loading wire:target="createCollection">
                                    <svg class="animate-spin h-5 w-5 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    {{ __('Création...') }}
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal d'upload de document -->
    <div 
        x-data="{ 
            show: @entangle('isUploadModalOpen'),
            closeModal() {
                this.show = false;
                $wire.closeUploadModal();
            }
        }"
        x-show="show"
        x-on:closeUploadModalAfterDelay.window="setTimeout(() => { closeModal() }, 1000)"
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
                            {{ __('Ajouter un document à la collection') }} "{{ $selectedCollection }}"
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
                            <label for="documentTitle" class="block text-sm font-medium text-custom-black dark:text-custom-white mb-1">
                                {{ __('Titre du document') }}
                            </label>
                            <input 
                                type="text" 
                                id="documentTitle" 
                                wire:model="documentTitle" 
                                class="w-full border border-custom-mid rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-custom-mid bg-custom-white text-custom-black dark:text-custom-white dark:border-custom-white dark:bg-custom-light-dark-mode"
                                placeholder="{{ __('Entrez un titre pour le document') }}"
                                required
                            >
                            @error('documentTitle') 
                                <span class="text-red-500 text-xs mt-1">{{ $message }}</span> 
                            @enderror
                        </div>
                        
                        <!-- Sélection du fichier -->
                        <div class="mb-4">
                            <label for="document" class="block text-sm font-medium text-custom-black dark:text-custom-white mb-1">
                                {{ __('Fichier (.txt, .pdf, .docx)') }}
                            </label>
                            <input 
                                type="file" 
                                id="document" 
                                wire:model="document" 
                                class="w-full border border-custom-mid rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-custom-mid bg-custom-white text-custom-black dark:text-custom-white dark:border-custom-white dark:bg-custom-light-dark-mode"
                                accept=".txt,.pdf,.docx"
                                required
                            >
                            @error('document') 
                                <span class="text-red-500 text-xs mt-1">{{ $message }}</span> 
                            @enderror
                        </div>
                        
                        <!-- Message de statut -->
                        @if($statusMessage && $isUploadModalOpen)
                            <div class="mb-4 p-2 rounded-lg {{ $success ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
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
