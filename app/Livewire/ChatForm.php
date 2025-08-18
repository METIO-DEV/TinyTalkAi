<?php

namespace App\Livewire;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\ConversationMemoryService;
use App\Services\RagService;
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
    public bool $ragEnabled = true;

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
    protected $listeners = [
        'modelSelected' => 'updateSelectedModel',
        'conversationSelected' => 'loadConversation',
        'conversationCleared' => 'clearConversation',
        'summarizingStarted' => 'onSummarizingStarted',
        'summarizingEnded' => 'onSummarizingEnded',
        'ragToggled' => 'toggleRag',
        'documentAdded' => 'handleDocumentAdded',
        'getAvailableModels' => 'sendAvailableModels',
        'collectionSelected' => 'updateSelectedCollection',
        'messageLoadingStarted' => '$refresh',
    ];

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
        $this->ragEnabled = session('rag_enabled', true);
        $this->selectedCollection = session('selected_collection', null);

        // Charger la conversation si une ID est présente dans la session
        $conversationId = session('selected_conversation_id');
        if ($conversationId) {
            $this->loadConversation($conversationId);
        }
    }

    /**
     * Active ou désactive le mode RAG
     */
    public function toggleRag(bool $enabled)
    {
        $this->ragEnabled = $enabled;
        session(['rag_enabled' => $enabled]);
    }

    /**
     * Appelé lorsqu'un document est ajouté
     */
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
    public function updateSelectedModel(string $modelName)
    {
        $this->selectedModel = $modelName;

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
    public function onSummarizingStarted()
    {
        $this->isSummarizing = true;
    }

    /**
     * Appelé quand un résumé se termine
     */
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
            return;
        }

        // Activer l'indicateur de chargement
        $this->isLoading = true;
        // Informer les autres composants que l'envoi commence
        $this->dispatch('messageLoadingStarted');

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
                if (!empty($this->selectedCollection)) {
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
                    
                    if (!empty($contextDocuments)) {
                        Log::info('Documents trouvés dans la collection', [
                            'collection' => $this->selectedCollection,
                            'document_count' => count($contextDocuments),
                        ]);
                        
                        $ragInfoMessage = ' avec contexte RAG (collection: ' . $this->selectedCollection . ', ' . count($contextDocuments) . ' documents)';
                    }
                } else if ($conversation) {
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
                    if (!empty($documentIds)) {
                        $contextDocuments = $this->ragService->searchSimilarDocuments($userMessage, 4, $documentIds);

                        if (!empty($contextDocuments)) {
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
                        $systemPrompt = "<<<SYSTEM
                            📌 Aucun document n’est actuellement associé à cette conversation alors que le mode RAG est activé.

                            ℹ️ Tant qu’aucun document n’est disponible et que RAG reste actif, **ne génère pas de réponse de fond basée sur tes connaissances internes**. Contente-toi d’informer l’utilisateur de la situation et de lui proposer les options ci-dessous.

                            ### Options à proposer à l’utilisateur
                            1. **Joindre un ou plusieurs documents** pertinents à cette conversation pour qu’ils puissent être pris en compte dans la recherche.
                            2. **Sélectionner une collection documentaire** existante dans les paramètres, tout en gardant le mode RAG activé.
                            3. **Désactiver temporairement le mode RAG** afin d’obtenir une réponse fondée uniquement sur les connaissances générales du modèle.

                            SYSTEM";
                    }
                }
            }

            // Préparer le prompt enrichi avec le contexte RAG si disponible
            if (!isset($systemPrompt)) {
                $systemPrompt = '';
                if (!empty($contextDocuments)) {
                    $systemPrompt = "Voici des extraits de documents pertinents pour répondre à la question:\n\n";
    
                    foreach ($contextDocuments as $index => $doc) {
                        $systemPrompt .= 'Contexte '.($index + 1).":\n".$doc['text']."\n\n";
                    }
    
                    $systemPrompt .= "Utilise ces informations pour enrichir ta réponse à la question de l'utilisateur.";
                } else {
                    $systemPrompt = "Aucun document pertinent n'a été trouvé dans la base de connaissances pour cette question. Commence ta réponse en indiquant brièvement que tu réponds selon tes connaissances générales car aucune information spécifique n'a été trouvée dans les documents fournis par l'utilisateur. Puis réponds au mieux à la question posée.";
                }
            }

            // Ajouter le contexte comme message système
            array_unshift($messages, [
                'role' => 'system',
                'content' => $systemPrompt,
            ]);

            // Configuration de l'API Ollama
            $ollamaHost = config('services.ollama.host', 'localhost');
            $ollamaPort = config('services.ollama.port', '11434');
            $ollamaUrl = 'http://'.$ollamaHost.':'.$ollamaPort.'/api/chat';

            Log::info('Envoi du message à Ollama'.$ragInfoMessage.': '.$ollamaUrl);

            // Préparation du prompt avec le contexte
            $response = Http::timeout(600)->post($ollamaUrl, [
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
                            'rag_enabled' => $this->ragEnabled, // Indiquer si le RAG était activé
                            'rag_documents' => ! empty($contextDocuments) ? count($contextDocuments) : 0, // Nombre de documents RAG utilisés
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

            // Informer les autres composants que l'envoi est terminé
            $this->dispatch('messageLoadingEnded');

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
