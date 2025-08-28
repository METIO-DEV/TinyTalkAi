<?php

namespace App\Livewire;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Component;

class ChatMessages extends Component
{
    /**
     * Les messages de la conversation
     */
    public array $messages = [];

    /**
     * L'ID de la conversation actuelle
     */
    public ?string $conversationId = null;

    /**
     * Le modèle actuellement sélectionné
     */
    public string $selectedModel = '';

    /**
     * Écoute les événements
     */
    protected $listeners = [
        'modelSelected' => 'updateSelectedModel',
        'conversationSelected' => 'loadConversation',
        'conversationCleared' => 'clearConversation',
        'messageAdded' => 'addMessage',
        'conversationLoaded' => 'handleConversationLoaded',
        'conversationUpdated' => 'syncConversationId',
        'refreshMessages' => 'refreshMessages',
    ];

    /**
     * Initialisation du composant
     */
    public function mount()
    {
        $this->selectedModel = session('selected_model', '');

        // Charger la conversation si une ID est présente dans la session
        $conversationId = session('selected_conversation_id');
        if ($conversationId) {
            $this->loadConversation($conversationId);
        }
    }

    /**
     * Met à jour le modèle sélectionné
     */
    public function updateSelectedModel(string $modelName)
    {
        $this->selectedModel = $modelName;
        $this->clearConversation();
    }

    /**
     * Charge une conversation
     */
    public function loadConversation(string $conversationId)
    {
        try {
            // Vérifier que l'utilisateur est connecté
            if (! Auth::check()) {
                return;
            }

            // Récupérer la conversation depuis la base de données
            $conversation = Conversation::where('id', $conversationId)
                ->where('user_id', Auth::id())
                ->first();

            if (! $conversation) {
                Log::error('ChatMessages: Tentative de chargement d\'une conversation inexistante: '.$conversationId);

                return;
            }

            $this->conversationId = $conversationId;

            // Mettre à jour le modèle sélectionné si disponible dans la conversation
            if ($conversation->model_name) {
                $this->selectedModel = $conversation->model_name;
            }

            // Note: Les messages seront chargés via l'événement conversationLoaded
            // émis par ChatForm pour éviter le double chargement
        } catch (\Exception $e) {
            Log::error('ChatMessages: Erreur lors du chargement de la conversation: '.$e->getMessage());
            $this->messages[] = [
                'role' => 'error',
                'content' => 'Erreur lors du chargement de la conversation: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Efface la conversation actuelle
     */
    public function clearConversation()
    {
        $this->conversationId = null;
        $this->messages = [];
    }

    /**
     * Ajoute un message à la liste des messages
     */
    #[On('messageAdded')]
    public function addMessage(array $message)
    {
        Log::info('ChatMessages: Ajout d\'un message', [
            'role' => $message['role'] ?? 'unknown',
            'content_length' => strlen($message['content'] ?? ''),
            'messages_count_before' => count($this->messages),
            'conversation_id' => $this->conversationId,
        ]);

        $this->messages[] = $message;

        Log::info('ChatMessages: Message ajouté', [
            'messages_count_after' => count($this->messages),
            'last_message_role' => end($this->messages)['role'] ?? 'unknown',
        ]);

        // Si on a une conversation active, recharger les messages depuis la BD
        if ($this->conversationId && Auth::check()) {
            $this->refreshMessagesFromDatabase();
        }
    }

    /**
     * Recharge les messages depuis la base de données
     */
    private function refreshMessagesFromDatabase()
    {
        try {
            $conversation = Conversation::where('id', $this->conversationId)
                ->where('user_id', Auth::id())
                ->first();

            if ($conversation) {
                $dbMessages = $conversation->messages()
                    ->orderBy('created_at')
                    ->get()
                    ->map(function ($message) {
                        return [
                            'role' => $message->role,
                            'content' => $message->content,
                        ];
                    })
                    ->toArray();

                Log::info('ChatMessages: Messages rechargés depuis la BD', [
                    'db_messages_count' => count($dbMessages),
                    'current_messages_count' => count($this->messages),
                ]);

                // Remplacer les messages actuels par ceux de la BD
                $this->messages = $dbMessages;
            }
        } catch (\Exception $e) {
            Log::error('ChatMessages: Erreur lors du rechargement des messages: '.$e->getMessage());
        }
    }

    /**
     * Recharge les messages depuis la base de données (appelée périodiquement)
     */
    #[On('refreshMessages')]
    public function refreshMessages()
    {
        if ($this->conversationId && Auth::check()) {
            Log::info('ChatMessages: Rechargement périodique des messages', [
                'conversation_id' => $this->conversationId,
                'current_count' => count($this->messages),
            ]);

            $this->refreshMessagesFromDatabase();
        }
    }

    /**
     * Gère les messages chargés depuis une conversation
     */
    public function handleConversationLoaded(array $messages)
    {
        $this->messages = $messages;
    }

    /**
     * Synchronise l'ID de conversation depuis ChatForm
     */
    public function syncConversationId()
    {
        $sessionConversationId = session('selected_conversation_id');
        if ($sessionConversationId && $sessionConversationId !== $this->conversationId) {
            Log::info('ChatMessages: Synchronisation du conversationId', [
                'old_id' => $this->conversationId,
                'new_id' => $sessionConversationId,
            ]);
            $this->conversationId = $sessionConversationId;
        }
    }

    public function render()
    {
        return view('livewire.chat-messages');
    }
}
