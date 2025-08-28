<div id="chat-messages" class="flex-1 overflow-y-auto space-y-4 p-6 dark:text-custom-white ">
    @if($selectedModel)
        <div id="model-selection-message" class="xl:p-4 p-0 xl:my-4 my-0 text-center">
            <p class="text-custom-black dark:text-custom-white font-bold text-lg mt-1">{{ $selectedModel }}</p>
        </div>
    @endif
    
    @forelse($parsedMessages as $message)
        @if($message['role'] === 'user')
            <div class="flex justify-end mb-4" wire:key="msg-user-{{ $loop->index }}-{{ substr(md5(($message['content'] ?? '') . $loop->index), 0, 8) }}">
                <div class="bg-custom-black text-white dark:text-custom-white dark:bg-custom-white-dark-mode rounded-tl-xl rounded-tr-xl rounded-bl-xl rounded-br-sm py-2 px-4 max-w-[80%]">
                    <div class="whitespace-pre-wrap">{{ $message['content'] }}</div>
                </div>
            </div>
        @elseif($message['role'] === 'assistant')
            <div class="flex justify-start mb-4 animate-fade-in" wire:key="msg-assistant-{{ $loop->index }}-{{ substr(md5(($message['content'] ?? '') . $loop->index), 0, 8) }}">
                <div class="bg-custom-light text-custom-black rounded-lg py-2 px-4 max-w-[80%] rounded-tl-xl rounded-tr-xl rounded-bl-sm rounded-br-xl">
                    @php($segments = $message['segments'] ?? [[ 'type' => 'text', 'content' => $message['content'] ?? '' ]])
                    @foreach($segments as $seg)
                        @if(($seg['type'] ?? 'text') === 'think')
                            <details class="my-2 group">
                                <summary class="cursor-pointer select-none text-sm text-gray-600 flex items-center gap-2">
                                    <svg class="w-4 h-4 transition-transform duration-200 group-open:rotate-90" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                    <span>{{ __('Processus de réflexion') }}</span>
                                </summary>
                                <div class="border border-custom-mid  text-custom-black rounded p-1 whitespace-pre-wrap text-xs">
                                    {{ $seg['content'] }}
                                </div>
                            </details>
                        @else
                            <div class="whitespace-pre-wrap">{{ $seg['content'] }}</div>
                        @endif
                    @endforeach
                </div>
            </div>
        @elseif($message['role'] === 'error')
            <div class="flex justify-center mb-4" wire:key="msg-error-{{ $loop->index }}-{{ substr(md5(($message['content'] ?? '') . $loop->index), 0, 8) }}">
                <div class="bg-red-100 border border-red-400 text-red-700 rounded-lg py-2 px-4 max-w-[80%]">
                    <div class="whitespace-pre-wrap">{{ $message['content'] }}</div>
                </div>
            </div>
        @endif
    @empty
        @if(!$selectedModel)
            <div class="text-center text-gray-500 dark:text-gray-400 py-8">
                <p class="text-lg font-medium">{{ __('Bienvenue sur TinyTalkAI') }}</p>
                <p class="mt-2">{{ __('Sélectionnez un modèle pour commencer une conversation') }}</p>
            </div>
        @endif
    @endforelse
</div>