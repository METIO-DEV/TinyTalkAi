<div>
    <div class="relative">
        <div id="history-container" class="space-y-2 overflow-y-auto max-h-60 bg-custom-white dark:bg-custom-light-dark-mode rounded-lg p-2">
            @if(count($conversations) > 0)
                @foreach($conversations as $conversation)
                    <div class="conversation-item px-3 py-2 rounded-md hover:bg-custom-light dark:hover:bg-custom-mid-dark-mode cursor-pointer transition-colors duration-200 flex justify-between items-center {{ $selectedConversationId == $conversation->id ? 'bg-custom-light dark:bg-custom-mid-dark-mode' : '' }}" 
                         wire:click="selectConversation('{{ $conversation->id }}')">
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-col">
                                <h4 class="text-sm font-medium text-custom-black dark:text-custom-white truncate">
                                    {{ $conversation->title ?? 'Conversation ' . substr($conversation->id, 0, 8) }}
                                </h4>
                                <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                    <p>{{ $conversation->model_name ?? 'Modèle inconnu' }}</p>  
                                </div>
                            </div>
                        </div>
                        <button class="delete-conversation-btn ml-2 text-gray-500 hover:text-red-500 dark:text-gray-400 dark:hover:text-red-400 transition-colors duration-200" 
                                wire:click.stop="deleteConversation('{{ $conversation->id }}')" 
                                title="Supprimer cette conversation">
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                @endforeach
            @else
                <div class="text-sm text-gray-500 dark:text-gray-300 px-3 py-2 text-center">
                    Aucune conversation enregistrée
                </div>
            @endif
        </div>
    </div>
</div>
