<div id="chat-messages" class="flex-1 overflow-y-auto space-y-4 p-6 dark:text-custom-white ">
    @if($selectedModel)
        <div id="model-selection-message" class="p-4 my-4 text-center">
            <p class="text-custom-black dark:text-custom-white font-bold text-lg mt-1">{{ $selectedModel }}</p>
        </div>
    @endif
    
    @forelse($messages as $message)
        @if($message['role'] === 'user')
            <div class="flex justify-end mb-4">
                <div class="bg-custom-black text-white dark:text-custom-white dark:bg-custom-white-dark-mode rounded-tl-xl rounded-tr-xl rounded-bl-xl rounded-br-sm py-2 px-4 max-w-[80%]">
                    <div class="whitespace-pre-wrap">{{ $message['content'] }}</div>
                </div>
            </div>
        @elseif($message['role'] === 'assistant')
            <div class="flex justify-start mb-4 animate-fade-in">
                <div class="bg-custom-light text-custom-black rounded-lg py-2 px-4 max-w-[80%] rounded-tl-xl rounded-tr-xl rounded-bl-sm rounded-br-xl">
                    <div class="whitespace-pre-wrap">{{ $message['content'] }}</div>
                </div>
            </div>
        @elseif($message['role'] === 'error')
            <div class="flex justify-center mb-4">
                <div class="bg-red-100 border border-red-400 text-red-700 rounded-lg py-2 px-4 max-w-[80%]">
                    <div class="whitespace-pre-wrap">{{ $message['content'] }}</div>
                </div>
            </div>
        @endif
    @empty
        @if(!$selectedModel)
            <div class="text-center text-gray-500 dark:text-gray-400 py-8">
                <p class="text-lg font-medium">Bienvenue sur TinyTalkAI</p>
                <p class="mt-2">Sélectionnez un modèle pour commencer une conversation</p>
            </div>
        @endif
    @endforelse
</div>