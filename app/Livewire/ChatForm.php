<?php

namespace App\Livewire;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\ConversationMemoryService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class ChatForm extends Component
{
    /**
     * Le message à envoyer
     */
    public string $message = '';

    /**
     * Le modèle actuellement sélectionné
     */
    public string $selectedModel = '';

    /**
     * L'ID de la conversation actuelle
     */
    public ?string $conversationId = null;

    /**
     * Indicateur de chargement pendant l'envoi d'un message
     */
    public bool $isLoading = false;

    /**
     * Service de gestion de la mémoire conversationnelle
     */
    protected ConversationMemoryService $memoryService;

    /**
     * Écoute les événements
     */
    protected $listeners = [
        'modelSelected' => 'updateSelectedModel',
        'conversationSelected' => 'loadConversation',
        'conversationCleared' => 'clearConversation',
        'loadingComplete' => '$refresh', // Ajout de l'événement loadingComplete
    ];

    /**
     * Constructeur du composant
     */
    public function boot(ConversationMemoryService $memoryService)
    {
        $this->memoryService = $memoryService;
    }

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
    }

    /**
     * Charge une conversation
     */
    public function loadConversation(string $conversationId)
    {
        $this->conversationId = $conversationId;
        session(['selected_conversation_id' => $conversationId]);

        try {
            // Vérifier que l'utilisateur est connecté
            if (! Auth::check()) {
                return;
            }

            // Récupérer la conversation depuis la base de données
            $conversation = Conversation::where('id', $conversationId)
                ->where('user_id', Auth::id())
                ->first();

            if ($conversation) {
                // Mettre à jour le modèle sélectionné si disponible dans la conversation
                if ($conversation->model_name) {
                    $this->selectedModel = $conversation->model_name;
                    session(['selected_model' => $this->selectedModel]);

                    // Émettre un événement pour informer les autres composants du changement de modèle
                    $this->dispatch('modelSelected', $this->selectedModel);
                }

                // Récupérer les messages de la conversation
                $messagesCollection = Message::where('conversation_id', $conversationId)
                    ->orderBy('created_at', 'asc')
                    ->get();

                // Convertir la collection en tableau pour la compatibilité avec le template
                foreach ($messagesCollection as $message) {
                    $this->dispatch('messageAdded', [
                        'role' => $message->role,
                        'content' => $message->content,
                    ]);
                }

                // Émettre un événement pour mettre à jour le compteur de tokens
                if ($conversation->tokens > 0) {
                    $this->dispatch('tokensUpdated', $conversation->tokens);
                }
            }
        } catch (\Exception $e) {
            Log::error('Erreur lors du chargement de la conversation: '.$e->getMessage());
        }
    }

    /**
     * Envoie un message
     */
    public function sendMessage()
    {
        if (empty($this->message) || empty($this->selectedModel)) {
            return;
        }

        try {
            // Stocker le message avant de le vider
            $userMessage = $this->message;

            // Activer l'indicateur de chargement
            $this->isLoading = true;

            // Ajouter le message utilisateur à la liste des messages pour l'affichage immédiat
            $this->dispatch('messageAdded', ['role' => 'user', 'content' => $userMessage]);

            // Vider le champ de message immédiatement pour une meilleure UX
            $this->message = '';

            // Récupérer les valeurs des paramètres depuis la session ou utiliser les valeurs par défaut
            $temperature = session('temperature', 0.7);
            $maxTokens = session('max_tokens', 2048);

            // Gestion de la conversation
            $conversation = null;
            $isNewConversation = false;

            if (Auth::check()) {
                if ($this->conversationId) {
                    // Récupérer une conversation existante
                    $conversation = Conversation::where('id', $this->conversationId)
                        ->where('user_id', Auth::id())
                        ->first();
                }

                if (! $conversation) {
                    // Créer une nouvelle conversation
                    Log::info('Tentative de création d\'une nouvelle conversation');
                    try {
                        $conversation = Conversation::create([
                            'user_id' => Auth::id(),
                            'title' => substr($userMessage, 0, 50).(strlen($userMessage) > 50 ? '...' : ''),
                            'model_name' => $this->selectedModel,
                            'tokens' => 0, // Initialiser les tokens à 0
                        ]);
                        Log::info('Nouvelle conversation créée avec ID: '.$conversation->id);
                        $isNewConversation = true;
                        $this->conversationId = $conversation->id;
                        session(['selected_conversation_id' => $this->conversationId]);

                        // Informer les autres composants qu'une nouvelle conversation a été créée
                        $this->dispatch('conversationSelected', $this->conversationId);
                    } catch (\Exception $e) {
                        Log::error('Erreur lors de la création de la conversation: '.$e->getMessage());
                    }
                }

                // Sauvegarder le message de l'utilisateur
                Message::create([
                    'conversation_id' => $conversation->id,
                    'role' => 'user',
                    'content' => $userMessage,
                    'settings' => [
                        'model' => $this->selectedModel,
                        'temperature' => $temperature,
                        'max_tokens' => $maxTokens,
                    ],
                ]);
            }

            // Récupérer la fenêtre locale (contexte des messages précédents)
            $messages = [];
            if ($conversation) {
                $messages = $this->memoryService->getLocalWindow($conversation->id);
            } else {
                // Si pas de conversation (utilisateur non authentifié), utiliser uniquement le message actuel
                $messages = [
                    [
                        'role' => 'user',
                        'content' => $userMessage,
                    ],
                ];
            }

            // Configuration de l'API Ollama
            $ollamaHost = config('services.ollama.host', 'localhost');
            $ollamaPort = config('services.ollama.port', '11434');
            $ollamaUrl = 'http://'.$ollamaHost.':'.$ollamaPort.'/api/chat';

            Log::info('Envoi du message à Ollama: '.$ollamaUrl);

            // Préparation du prompt avec le contexte
            $response = Http::timeout(120)->post($ollamaUrl, [
                'model' => $this->selectedModel,
                'messages' => $messages,
                'options' => [
                    'temperature' => (float) $temperature,
                    'max_tokens' => (int) $maxTokens,
                ],
                'stream' => false,
            ]);

            if ($response->successful()) {
                Log::info('Réponse reçue d\'Ollama avec succès');

                // Récupérer la réponse depuis le format de l'API /api/chat
                $aiResponse = $response->json('message.content');

                // Récupérer les informations de tokens depuis la réponse
                $promptTokens = $response->json('prompt_eval_count', 0);
                $responseTokens = $response->json('eval_count', 0);
                $totalTokens = $promptTokens + $responseTokens;

                // Émettre un événement pour mettre à jour le compteur de tokens
                $this->dispatch('tokensUpdated', $totalTokens);

                // Sauvegarder la réponse de l'assistant
                if (Auth::check() && $conversation) {
                    // Mettre à jour le compteur de tokens de la conversation
                    $conversation->increment('tokens', $totalTokens);

                    Message::create([
                        'conversation_id' => $conversation->id,
                        'role' => 'assistant',
                        'content' => $aiResponse,
                        'settings' => [
                            'model' => $this->selectedModel,
                            'temperature' => $temperature,
                            'max_tokens' => $maxTokens,
                            'tokens_used' => $totalTokens, // Stocker les tokens utilisés
                        ],
                    ]);
                }

                // Ajouter la réponse de l'IA à la liste des messages
                $this->dispatch('messageAdded', ['role' => 'assistant', 'content' => $aiResponse]);

                // Émettre un événement pour mettre à jour la liste des conversations
                $this->dispatch('conversationUpdated');

            } else {
                // Gérer les erreurs HTTP
                $errorMessage = 'Erreur HTTP: '.$response->status().' - '.$response->body();
                $this->dispatch('messageAdded', ['role' => 'error', 'content' => $errorMessage]);
                Log::error($errorMessage);
            }
        } catch (\Exception $e) {
            $errorMessage = 'Erreur lors de l\'envoi du message: '.$e->getMessage();
            $this->dispatch('messageAdded', ['role' => 'error', 'content' => $errorMessage]);
            Log::error($errorMessage);
        } catch (\Throwable $t) {
            // Capturer toutes les autres erreurs potentielles (y compris les erreurs fatales)
            $errorMessage = 'Erreur fatale lors de l\'envoi du message: '.$t->getMessage();
            $this->dispatch('messageAdded', ['role' => 'error', 'content' => $errorMessage]);
            Log::error($errorMessage);
        } finally {
            // Désactiver l'indicateur de chargement
            $this->isLoading = false;

            // Forcer la mise à jour du composant pour s'assurer que l'état est reflété dans l'UI
            $this->dispatch('loadingComplete');
        }
    }

    /**
     * Efface la conversation actuelle
     */
    public function clearConversation()
    {
        $this->conversationId = null;

        // S'assurer que l'indicateur de chargement est désactivé
        $this->isLoading = false;
    }

    public function render()
    {
        return view('livewire.chat-form');
    }
}
