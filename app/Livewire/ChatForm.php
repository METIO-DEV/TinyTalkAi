<?php

namespace App\Livewire;

use App\Models\AIModel;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\ConversationMemoryService;
use App\Services\RagService;
use App\Support\ChatMessageContent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
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
     * Service RAG pour la recherche de contexte
     */
    protected RagService $ragService;

    /**
     * Indique si un résumé est en cours dans un autre composant
     */
    public bool $isSummarizing = false;

    /**
     * Indique si le mode RAG est activé
     */
    public bool $ragEnabled = false;

    /**
     * Liste des modèles disponibles
     */
    public array $availableModels = [];

    /**
     * Nom de la collection Qdrant sélectionnée
     */
    public ?string $selectedCollection = null;

    /**
     * Écouteurs d'événements Livewire
     */
    // protected $listeners = [
    //     'modelSelected' => 'updateSelectedModel',
    //     'conversationSelected' => 'loadConversation',
    //     'conversationCleared' => 'clearConversation',
    //     'summarizingStarted' => 'onSummarizingStarted',
    //     'summarizingEnded' => 'onSummarizingEnded',
    //     'ragToggled' => 'toggleRag',
    //     'documentAdded' => 'handleDocumentAdded',
    //     'getAvailableModels' => 'sendAvailableModels',
    //     'collectionSelected' => 'updateSelectedCollection',
    //     'messageLoadingStarted' => '$refresh',
    // ];

    /**
     * Constructeur du composant
     */
    public function boot(ConversationMemoryService $memoryService, RagService $ragService)
    {
        $this->memoryService = $memoryService;
        $this->ragService = $ragService;
    }

    /**
     * Initialisation du composant
     */
    public function mount()
    {
        $this->selectedModel = session('selected_model', '');
        $this->ragEnabled = session('rag_enabled', false);
        $this->selectedCollection = session('selected_collection', null);

        // Vérifier si le modèle sélectionné est accessible pour l'utilisateur
        $this->checkModelAccess();

        // Notifier les autres composants de l'état initial
        $this->dispatch('ragToggled', $this->ragEnabled);
        $this->dispatch('collectionSelected', $this->selectedCollection);

        // Charger la conversation si une ID est présente dans la session
        $conversationId = session('selected_conversation_id');
        if ($conversationId) {
            $this->loadConversation($conversationId);
        }
    }

    /**
     * Vérifie si l'utilisateur a accès au modèle sélectionné
     * Si non, sélectionne un modèle accessible par défaut
     */
    private function checkModelAccess()
    {
        // Si aucun modèle n'est sélectionné ou si l'utilisateur est admin, pas besoin de vérifier
        if (empty($this->selectedModel) || ! Auth::check() || (Auth::check() && Auth::user()->hasRole('admin'))) {
            return;
        }

        try {
            $user = Auth::user();

            // Vérifier si l'utilisateur a des groupes
            if ($user->groups->isEmpty()) {
                // L'utilisateur n'a pas de groupes, donc pas d'accès aux modèles
                $this->selectedModel = '';
                session(['selected_model' => '']);

                // Notifier l'utilisateur
                $this->dispatch('showNotification', [
                    'type' => 'error',
                    'message' => 'Vous n\'avez accès à aucun modèle car vous n\'appartenez à aucun groupe. Veuillez contacter un administrateur.',
                ]);

                Log::error('Utilisateur sans groupe, aucun modèle accessible', [
                    'user_id' => $user->id,
                ]);

                return;
            }

            $userGroups = $user->groups->pluck('id')->toArray();

            // Vérifier si le modèle sélectionné est accessible pour l'utilisateur
            $modelExists = AIModel::where('full_name', $this->selectedModel)
                ->where('is_active', true)
                ->whereHas('groups', function ($query) use ($userGroups) {
                    $query->whereIn('groups.id', $userGroups);
                })
                ->exists();

            if (! $modelExists) {
                Log::warning('Modèle non accessible pour l\'utilisateur, recherche d\'un modèle alternatif', [
                    'user_id' => $user->id,
                    'selected_model' => $this->selectedModel,
                    'user_groups' => $userGroups,
                ]);

                // Trouver un modèle accessible pour l'utilisateur
                $alternativeModel = AIModel::where('is_active', true)
                    ->whereHas('groups', function ($query) use ($userGroups) {
                        $query->whereIn('groups.id', $userGroups);
                    })
                    ->orderBy('name')
                    ->first();

                if ($alternativeModel) {
                    $this->selectedModel = $alternativeModel->full_name;
                    session(['selected_model' => $this->selectedModel]);

                    // Émettre un événement pour informer les autres composants
                    $this->dispatch('modelSelected', $this->selectedModel);

                    // Notifier l'utilisateur
                    $this->dispatch('showNotification', [
                        'type' => 'warning',
                        'message' => 'Le modèle précédemment sélectionné n\'est pas accessible. Un modèle alternatif a été sélectionné automatiquement.',
                    ]);

                    Log::info('Modèle alternatif sélectionné', [
                        'user_id' => $user->id,
                        'new_model' => $this->selectedModel,
                    ]);
                } else {
                    // Aucun modèle accessible trouvé
                    $this->selectedModel = '';
                    session(['selected_model' => '']);

                    // Notifier l'utilisateur
                    $this->dispatch('showNotification', [
                        'type' => 'error',
                        'message' => 'Aucun modèle accessible n\'a été trouvé pour votre compte. Veuillez contacter un administrateur.',
                    ]);

                    Log::error('Aucun modèle accessible pour l\'utilisateur', [
                        'user_id' => $user->id,
                        'user_groups' => $userGroups,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Erreur lors de la vérification de l\'accès au modèle: '.$e->getMessage());
        }
    }

    /**
     * Active ou désactive le mode RAG
     */
    #[On('ragToggled')]
    public function toggleRag(bool $enabled)
    {
        $this->ragEnabled = $enabled;
        session(['rag_enabled' => $enabled]);

        // Si RAG est désactivé, réinitialiser la collection sélectionnée
        if (! $enabled) {
            $this->selectedCollection = null;
            session(['selected_collection' => null]);
            Log::info('Collection réinitialisée car RAG désactivé');
        }
    }

    /**
     * Appelé lorsqu'un document est ajouté
     */
    #[On('documentAdded')]
    public function handleDocumentAdded($documentId)
    {
        // Activer automatiquement le mode RAG après l'ajout d'un document
        session(['rag_enabled' => true]);
        $this->ragEnabled = true;

        // Afficher un message de confirmation
        $this->dispatch('showNotification', [
            'type' => 'success',
            'message' => 'Document ajouté avec succès! Le mode RAG a été activé.',
        ]);
    }

    /**
     * Envoie la liste des modèles disponibles aux autres composants
     */
    public function sendAvailableModels()
    {
        // Cette méthode n'est plus nécessaire car DocumentUploader récupère
        // directement les modèles d'embedding via RagService
        // Mais on la garde pour compatibilité avec d'autres composants qui pourraient l'utiliser
    }

    /**
     * Met à jour le modèle sélectionné
     */
    #[On('modelSelected')]
    public function updateSelectedModel(string $modelName)
    {
        $this->selectedModel = $modelName;

        // Vérifier si l'utilisateur a accès à ce modèle
        $this->checkModelAccess();

        // Réinitialiser l'ID de conversation seulement si l'événement vient de la sélection d'un modèle
        // et non pas du chargement d'une conversation existante
        if (! $this->conversationId || session('model_selection_source') === 'sidebar') {
            $this->conversationId = null;
            session()->forget('selected_conversation_id');
        }

        // Réinitialiser la source de sélection du modèle
        session()->forget('model_selection_source');
    }

    /**
     * Met à jour la collection Qdrant sélectionnée
     */
    #[On('collectionSelected')]
    public function updateSelectedCollection(?string $collectionName)
    {
        $this->selectedCollection = $collectionName;

        // Stocker la collection sélectionnée dans la session
        session(['selected_collection' => $collectionName]);

        Log::info('Collection sélectionnée mise à jour dans ChatForm', [
            'collection' => $collectionName,
            'session_value' => session('selected_collection'),
        ]);

        // Définir explicitement la collection dans le RagService
        if ($collectionName) {
            $this->ragService->setQdrantCollection($collectionName);
            Log::info('Collection définie dans RagService', [
                'collection' => $collectionName,
            ]);
        }
    }

    /**
     * Charge une conversation
     */
    #[On('conversationSelected')]
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

                    // Vérifier si l'utilisateur a toujours accès à ce modèle
                    $this->checkModelAccess();

                    // Émettre un événement pour informer les autres composants du changement de modèle
                    $this->dispatch('modelSelected', $this->selectedModel);
                }

                // Récupérer les messages de la conversation
                $messagesCollection = Message::where('conversation_id', $conversationId)
                    ->orderBy('created_at', 'asc')
                    ->get();

                // Convertir la collection en tableau pour la compatibilité avec le template
                $messages = [];
                foreach ($messagesCollection as $message) {
                    $messages[] = [
                        'role' => $message->role,
                        'content' => $message->content,
                    ];
                }

                // Émettre un événement pour afficher les messages dans l'interface
                $this->dispatch('conversationLoaded', $messages);

                // Émettre un événement pour mettre à jour le compteur de tokens
                $this->dispatch('tokensUpdated', $conversation->tokens);
            }
        } catch (\Exception $e) {
            Log::error('Erreur lors du chargement de la conversation: '.$e->getMessage());
        }
    }

    /**
     * Appelé quand un résumé commence
     */
    #[On('summarizingStarted')]
    public function onSummarizingStarted()
    {
        $this->isSummarizing = true;
    }

    /**
     * Appelé quand un résumé se termine
     */
    #[On('summarizingEnded')]
    public function onSummarizingEnded()
    {
        $this->isSummarizing = false;
    }

    /**
     * Envoie un message
     */
    public function sendMessage()
    {
        // Vérifier si une requête est déjà en cours
        if ($this->isLoading || $this->isSummarizing) {
            Log::info('Tentative d\'envoi de message ignorée car une requête est déjà en cours');

            $this->dispatch('notify', [
                'type' => 'info',
                'message' => 'Une requête est déjà en cours de traitement, veuillez patienter.',
            ]);

            return;
        }

        // Vérifier si un message est présent
        if (empty(trim($this->message))) {
            return;
        }

        // Vérifier si un modèle est sélectionné
        if (empty($this->selectedModel)) {
            // Notifier l'utilisateur qu'aucun modèle n'est disponible
            $this->dispatch('showNotification', [
                'type' => 'error',
                'message' => 'Aucun modèle n\'est disponible. Veuillez contacter un administrateur pour obtenir l\'accès à des modèles.',
            ]);

            Log::error('Tentative d\'envoi de message sans modèle sélectionné', [
                'user_id' => Auth::check() ? Auth::id() : 'non connecté',
            ]);

            return;
        }

        // Activer l'indicateur de chargement
        $this->isLoading = true;
        // Informer les autres composants que l'envoi commence
        $this->dispatch('messageLoadingStarted')->to(\App\Livewire\TokenCounter::class);

        try {
            // Stocker le message avant de le vider
            $userMessage = $this->message;

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
                        // Rafraîchir la liste des conversations immédiatement
                        $this->dispatch('conversationUpdated');
                    } catch (\Exception $e) {
                        Log::error('Erreur lors de la création de la conversation: '.$e->getMessage());
                    }
                }

                // Sauvegarder le message de l'utilisateur
                Message::create([
                    'conversation_id' => $conversation->id,
                    'role' => 'user',
                    'content' => $userMessage,
                    'content_parts' => ChatMessageContent::fromText($userMessage),
                    'settings' => [
                        'model' => $this->selectedModel,
                        'temperature' => $temperature,
                        'max_tokens' => $maxTokens,
                    ],
                ]);

                // Mettre à jour updated_at de la conversation pour l'ordre dans l'historique
                try {
                    $conversation->touch();
                } catch (\Exception $e) {
                    Log::warning('Impossible de mettre à jour updated_at de la conversation: '.$e->getMessage());
                }

                // Rafraîchir la liste des conversations après le message utilisateur
                $this->dispatch('conversationUpdated');
            }

            // Récupérer la fenêtre locale (contexte des messages précédents)
            $messages = [];
            if ($conversation) {
                // Vérifier s'il existe un résumé pour cette conversation
                if ($conversation->summary_flag && $conversation->summary) {
                    // Ajouter le résumé comme message système en premier
                    $messages[] = [
                        'role' => 'system',
                        'content' => "Résumé de la conversation précédente: {$conversation->summary}",
                    ];

                    Log::info('Résumé ajouté au contexte', [
                        'conversation_id' => $conversation->id,
                        'summary_length' => strlen($conversation->summary),
                        'summary_message_id' => $conversation->summary_message_id,
                    ]);
                }

                // Récupérer les messages de la fenêtre locale (déjà filtrés par summary_message_id)
                $localWindow = $this->memoryService->getLocalWindow($conversation->id);
                $messages = array_merge($messages, $localWindow);

                Log::info('Fenêtre locale récupérée', [
                    'conversation_id' => $conversation->id,
                    'message_count' => count($localWindow),
                ]);
            }

            // Préparer le contexte RAG si activé
            $contextDocuments = [];
            $ragInfoMessage = '';

            // Logs de diagnostic pour le RAG
            Log::info('Diagnostic RAG avant vérification des conditions', [
                'rag_enabled' => $this->ragEnabled,
                'rag_enabled_type' => gettype($this->ragEnabled),
                'selected_collection' => $this->selectedCollection,
                'selected_collection_type' => gettype($this->selectedCollection),
                'session_collection' => session('selected_collection'),
                'session_rag_enabled' => session('rag_enabled'),
            ]);

            if ($this->ragEnabled) {
                // Log de diagnostic pour la collection sélectionnée
                Log::info('État RAG avant recherche', [
                    'rag_enabled' => $this->ragEnabled,
                    'selected_collection' => $this->selectedCollection,
                    'session_collection' => session('selected_collection'),
                    'conversation_id' => $this->conversationId,
                ]);

                // Déterminer le mode RAG à utiliser (collection ou conversation)
                if (! empty($this->selectedCollection)) {
                    // Mode RAG par collection
                    Log::info('Utilisation du RAG par collection', [
                        'collection' => $this->selectedCollection,
                        'query' => $userMessage,
                    ]);

                    // Log juste avant l'appel à searchSimilarDocuments
                    Log::info('Paramètres de recherche RAG par collection', [
                        'collection' => $this->selectedCollection,
                        'query' => $userMessage,
                        'limit' => 4,
                        'document_ids' => null,
                    ]);

                    // Rechercher dans la collection spécifiée
                    $contextDocuments = $this->ragService->searchSimilarDocuments(
                        $userMessage,
                        4,
                        null,
                        $this->selectedCollection
                    );

                    if (! empty($contextDocuments)) {
                        Log::info('Documents trouvés dans la collection', [
                            'collection' => $this->selectedCollection,
                            'document_count' => count($contextDocuments),
                        ]);

                        $ragInfoMessage = ' avec contexte RAG (collection: '.$this->selectedCollection.', '.count($contextDocuments).' documents)';
                    }
                } elseif ($conversation) {
                    // Mode RAG par conversation (existant)
                    $documentIds = [];
                    if ($conversation) {
                        $documentIds = $conversation->getDocumentIds();
                        Log::info('Documents associés à la conversation', [
                            'conversation_id' => $conversation->id,
                            'document_count' => count($documentIds),
                            'document_ids' => $documentIds,
                        ]);
                    }

                    // Rechercher les documents pertinents uniquement si des documents sont liés à la conversation
                    if (! empty($documentIds)) {
                        $contextDocuments = $this->ragService->searchSimilarDocuments($userMessage, 4, $documentIds);

                        if (! empty($contextDocuments)) {
                            Log::info('Documents pertinents trouvés', [
                                'document_count' => count($contextDocuments),
                                'query' => $userMessage,
                            ]);

                            // Ajouter les informations sur les documents trouvés dans les logs
                            foreach ($contextDocuments as $index => $doc) {
                                Log::info("Document RAG #{$index}", [
                                    'document_id' => $doc['document_id'],
                                    'score' => $doc['score'],
                                    'text_preview' => substr($doc['text'], 0, 100).'...',
                                ]);
                            }

                            $ragInfoMessage = ' avec contexte RAG ('.count($contextDocuments).' documents)';
                        }
                    } else {
                        Log::info('Aucun document lié à la conversation, recherche RAG ignorée', [
                            'conversation_id' => $conversation->id,
                        ]);

                        // Prompt spécifique pour informer l'utilisateur qu'aucun document n'est lié
                        $systemPrompt = '<<<SYSTEM
                            📌 Aucun document n’est actuellement associé à cette conversation alors que le mode RAG est activé.

                            ℹ️ Tant qu’aucun document n’est disponible et que RAG reste actif, **ne génère pas de réponse de fond basée sur tes connaissances internes**. Contente-toi d’informer l’utilisateur de la situation et de lui proposer les options ci-dessous.

                            ### Options à proposer à l’utilisateur
                            1. **Joindre un ou plusieurs documents** pertinents à cette conversation pour qu’ils puissent être pris en compte dans la recherche.
                            2. **Sélectionner une collection documentaire** existante dans les paramètres, tout en gardant le mode RAG activé.
                            3. **Désactiver temporairement le mode RAG** afin d’obtenir une réponse fondée uniquement sur les connaissances générales du modèle.

                            SYSTEM';
                    }
                }
            }

            // Préparer le prompt enrichi avec le contexte RAG si disponible
            if (! isset($systemPrompt)) {
                $systemPrompt = '';
                if (! empty($contextDocuments)) {
                    $systemPrompt = "Voici des extraits de documents pertinents pour répondre à la question:\n\n";

                    foreach ($contextDocuments as $index => $doc) {
                        $systemPrompt .= 'Contexte '.($index + 1).":\n".$doc['text']."\n\n";
                    }

                    $systemPrompt .= "Utilise ces informations pour enrichir ta réponse à la question de l'utilisateur.";
                } else {
                    $systemPrompt = "Aucun document pertinent n'a été trouvé dans la base de connaissances pour cette question. N'indique pas que tu réponds selon tes connaissances générales. Réponds au mieux à la question posée.";
                }
            }

            // Ajouter le contexte comme message système
            array_unshift($messages, [
                'role' => 'system',
                'content' => $systemPrompt,
            ]);

            Log::info('Déclenchement du streaming'.$ragInfoMessage);

            // Déclencher l'événement de streaming avec toutes les données nécessaires
            $streamingData = [
                'model' => $this->selectedModel,
                'messages' => $messages,
                'conversationId' => $this->conversationId,
                'ragEnabled' => $this->ragEnabled,
                'selectedCollection' => $this->selectedCollection,
                'temperature' => $temperature,
                'maxTokens' => $maxTokens,
            ];

            $this->dispatch('startStreaming', $streamingData);

            Log::info('Événement startStreaming dispatché');

            // Désactiver l'indicateur de chargement
            $this->isLoading = false;
            $this->dispatch('messageLoadingEnded')->to(TokenCounter::class);

        } catch (\Exception $e) {
            $errorMessage = 'Erreur lors de la préparation du message: '.$e->getMessage();
            $this->dispatch('messageAdded', ['role' => 'error', 'content' => $errorMessage]);
            Log::error($errorMessage);
        }
    }

    /**
     * Écouteur pour sauvegarder le message de l'assistant après le streaming
     */
    #[On('saveAssistantMessage')]
    public function saveAssistantMessage($data)
    {
        try {
            if (Auth::check() && isset($data['conversationId']) && isset($data['content'])) {
                $conversation = Conversation::where('id', $data['conversationId'])
                    ->where('user_id', Auth::id())
                    ->first();

                if ($conversation) {

                    // Émettre un événement pour mettre à jour la liste des conversations
                    $this->dispatch('conversationUpdated');
                    // Émettre un événement pour mettre à jour le compteur de tokens
                    $this->dispatch('tokensUpdated', $data['tokens']);

                    // Ne pas réémettre le message assistant ici pour éviter les doublons UI.
                    // Le message final est désormais injecté depuis le handler SSE 'complete'.

                    Log::info('Message assistant traité après streaming', [
                        'conversation_id' => $conversation->id,
                        'tokens' => $data['tokens'] ?? 0,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Erreur lors de la sauvegarde du message assistant: '.$e->getMessage());
        } finally {
            // Désactiver l'indicateur de chargement
            $this->isLoading = false;
            $this->dispatch('messageLoadingEnded')->to(TokenCounter::class);
        }
    }

    /**
     * Efface la conversation actuelle
     */
    #[On('conversationCleared')]
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
