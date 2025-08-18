<?php

namespace App\Livewire;

use App\Models\Conversation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class ConversationHistory extends Component
{
    /**
     * Liste des conversations
     */
    public $conversations = [];

    /**
     * L'ID de la conversation actuellement sélectionnée
     */
    public ?string $selectedConversationId = null;

    /**
     * Écoute les événements
     */
    protected $listeners = [
        'conversationUpdated' => 'fetchConversations',
        'newConversation' => 'clearSelectedConversation',
    ];

    /**
     * Initialisation du composant
     */
    public function mount()
    {
        // Récupérer l'ID de conversation sélectionnée depuis la session
        $this->selectedConversationId = session('selected_conversation_id');

        // Récupérer la liste des conversations
        $this->fetchConversations();
    }

    /**
     * Récupère la liste des conversations
     */
    public function fetchConversations()
    {
        try {
            // Récupérer les conversations de l'utilisateur connecté depuis la base de données
            if (Auth::check()) {
                $this->conversations = Conversation::where('user_id', Auth::id())
                    ->orderBy('updated_at', 'desc')
                    ->get();
            } else {
                $this->conversations = collect([]);
            }
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des conversations: '.$e->getMessage());
            $this->conversations = collect([]);
        }
    }

    /**
     * Sélectionne une conversation
     */
    public function selectConversation(string $conversationId)
    {
        try {
            // Vérifier que la conversation existe
            $conversation = Conversation::find($conversationId);
            if (! $conversation) {
                Log::error('Tentative de sélection d\'une conversation inexistante: '.$conversationId);

                return;
            }

            $this->selectedConversationId = $conversationId;

            // Sauvegarder l'ID de la conversation dans la session
            session(['selected_conversation_id' => $conversationId]);

            // Émettre un événement pour informer les autres composants
            // Nous n'utilisons plus conversationCleared avant conversationSelected
            $this->dispatch('conversationSelected', $conversationId);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la sélection de la conversation: '.$e->getMessage());
        }
    }

    /**
     * Supprime une conversation
     */
    public function deleteConversation(string $conversationId)
    {
        try {
            // Vérifier que l'utilisateur est connecté
            if (! Auth::check()) {
                return;
            }

            // Récupérer la conversation à supprimer (en vérifiant qu'elle appartient bien à l'utilisateur)
            $conversation = Conversation::where('id', $conversationId)
                ->where('user_id', Auth::id())
                ->first();

            if ($conversation) {
                // Supprimer la conversation (les messages associés seront supprimés automatiquement si la relation est configurée avec onDelete cascade)
                $conversation->delete();

                // Si la conversation supprimée était la conversation sélectionnée
                if ($this->selectedConversationId === $conversationId) {
                    $this->selectedConversationId = null;
                    session()->forget('selected_conversation_id');

                    // Émettre un événement pour informer les autres composants
                    $this->dispatch('conversationCleared');
                }

                // Rafraîchir la liste des conversations
                $this->fetchConversations();
            }
        } catch (\Exception $e) {
            Log::error('Erreur lors de la suppression de la conversation: '.$e->getMessage());
        }
    }

    /**
     * Efface la conversation sélectionnée pour en créer une nouvelle
     */
    public function clearSelectedConversation()
    {
        $this->selectedConversationId = null;
        session()->forget('selected_conversation_id');

        // Émettre un événement pour informer les autres composants
        $this->dispatch('conversationCleared');
    }

    public function render()
    {
        return view('livewire.conversation-history');
    }
}
