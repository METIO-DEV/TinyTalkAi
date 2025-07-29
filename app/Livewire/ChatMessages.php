<?php

namespace App\Livewire;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
        'loadingComplete' => '$refresh',
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

            // Récupérer les messages de la conversation
            $messagesCollection = Message::where('conversation_id', $conversationId)
                ->orderBy('created_at', 'asc')
                ->get();

            // Convertir la collection en tableau pour la compatibilité avec le template
            $this->messages = [];
            foreach ($messagesCollection as $message) {
                $this->messages[] = [
                    'role' => $message->role,
                    'content' => $message->content,
                ];
            }
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
    public function addMessage(array $message)
    {
        $this->messages[] = $message;
    }

    public function render()
    {
        return view('livewire.chat-messages');
    }
}
