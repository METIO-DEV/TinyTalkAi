<?php

namespace App\Livewire;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\ConversationMemoryService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class TokenCounter extends Component
{
    /**
     * Le modèle actuellement sélectionné
     */
    public string $selectedModel = '';

    /**
     * La limite de tokens du modèle sélectionné
     */
    public ?int $tokenLimit = null;

    /**
     * L'ID de la conversation actuelle
     */
    public ?string $conversationId = null;

    /**
     * Le nombre total de tokens utilisés
     */
    public int $tokensUsed = 0;

    /**
     * Service de gestion de la mémoire des conversations
     */
    protected $memoryService;

    /**
     * Indique si un résumé est en cours de génération
     */
    public bool $isSummarizing = false;

    /**
     * Indique si un envoi de message est en cours dans un autre composant
     */
    public bool $isMessageSending = false;

    /**
     * Écoute les événements de mise à jour du modèle et des tokens
     */
    protected $listeners = [
        'modelSelected' => 'updateSelectedModel',
        'tokensUpdated' => 'updateTokensUsed',
        'conversationSelected' => 'loadConversation',
        'conversationCleared' => 'clearConversation',
        'loadingComplete' => '$refresh',
        'messageLoadingStarted' => 'onMessageLoadingStarted',
        'messageLoadingEnded' => 'onMessageLoadingEnded',
    ];

    /**
     * Initialisation du composant
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
        // Tenter de récupérer le modèle sélectionné depuis la session
        $this->selectedModel = session('selected_model', '');

        // Si un modèle est sélectionné, récupérer sa limite de tokens
        if ($this->selectedModel) {
            $this->fetchTokenLimit();
        }

        // Charger la conversation si une ID est présente dans la session
        $conversationId = session('selected_conversation_id');
        if ($conversationId) {
            $this->loadConversation($conversationId);
        }
    }

    /**
     * Met à jour le modèle sélectionné et sa limite de tokens
     */
    public function updateSelectedModel(string $modelName)
    {
        $this->selectedModel = $modelName;
        $this->tokensUsed = 0; // Réinitialiser le compteur lors du changement de modèle

        // Sauvegarder le modèle sélectionné dans la session
        session(['selected_model' => $modelName]);

        // Récupérer la limite de tokens pour ce modèle
        $this->fetchTokenLimit();
    }

    /**
     * Récupère la limite de tokens pour le modèle sélectionné
     */
    protected function fetchTokenLimit()
    {
        try {
            // Récupération des paramètres de configuration avec valeurs par défaut
            $ollamaHost = config('services.ollama.host', 'host.docker.internal');
            $ollamaPort = config('services.ollama.port', '11434');
            $ollamaUrl = 'http://'.$ollamaHost.':'.$ollamaPort.'/api/show';

            // Requête HTTP avec timeout court pour éviter de bloquer l'interface utilisateur
            $response = Http::timeout(3)->post($ollamaUrl, [
                'name' => $this->selectedModel,
            ]);

            // Vérification du succès de la requête (code 2xx)
            if ($response->successful()) {
                $modelDetails = $response->json();

                // Extraire la limite de tokens si disponible
                $tokenLimit = null;

                // Recherche récursive de tout champ contenant "context" dans model_info
                if (isset($modelDetails['model_info'])) {
                    $this->tokenLimit = $this->findContextLength($modelDetails['model_info']);
                    if ($this->tokenLimit) {
                        Log::info("Limite de tokens trouvée dans model_info: {$this->tokenLimit}");
                    }
                }

                if (! $this->tokenLimit) {
                    Log::warning("Aucune limite de tokens trouvée pour le modèle {$this->selectedModel}");
                }
            }
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération de la limite de tokens: '.$e->getMessage());
        }
    }

    /**
     * Recherche récursive d'un champ contenant "context" ou "ctx" dans un tableau
     *
     * @param  array  $data  Le tableau à parcourir
     * @return int|null La valeur trouvée ou null
     */
    protected function findContextLength($data)
    {
        if (! is_array($data)) {
            return null;
        }

        // Parcourir toutes les clés du tableau
        foreach ($data as $key => $value) {
            // Vérifier si la clé contient "context" ou "ctx"
            if (is_string($key) &&
                (stripos($key, 'context_length') !== false)) {

                // Si la valeur est numérique, c'est probablement ce qu'on cherche
                if (is_numeric($value)) {
                    return (int) $value;
                }
            }

            // Si la valeur est un tableau, rechercher récursivement
            if (is_array($value)) {
                $result = $this->findContextLength($value);
                if ($result !== null) {
                    return $result;
                }
            }
        }

        return null;
    }

    /**
     * Charge une conversation et met à jour le compteur de tokens
     */
    public function loadConversation(string $conversationId)
    {
        // Vérifier si la conversation existe avant de la charger
        try {
            $conversation = Conversation::find($conversationId);
            if (! $conversation) {
                Log::error('TokenCounter: Tentative de chargement d\'une conversation inexistante: '.$conversationId);

                return;
            }

            $this->conversationId = $conversationId;
            $this->updateTokensFromDatabase();
        } catch (\Exception $e) {
            Log::error('TokenCounter: Erreur lors du chargement de la conversation: '.$e->getMessage());
        }
    }

    /**
     * Efface la conversation actuelle
     */
    public function clearConversation()
    {
        // Seulement effacer si nous avons été explicitement informés de le faire
        $this->conversationId = null;
        $this->tokensUsed = 0;
    }

    /**
     * Met à jour le nombre de tokens utilisés depuis la base de données
     */
    protected function updateTokensFromDatabase()
    {
        if (! $this->conversationId) {
            // Ne pas réinitialiser les tokens ici, car cela pourrait causer un flash
            return;
        }

        try {
            $conversation = Conversation::find($this->conversationId);
            if ($conversation) {
                $this->tokensUsed = $conversation->tokens ?? 0;
                // Ajouter un délai pour éviter les problèmes de synchronisation
                usleep(50000); // 50ms
            }
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des tokens: '.$e->getMessage());
        }
    }

    /**
     * Met à jour le nombre de tokens utilisés
     */
    public function updateTokensUsed(int $tokensUsed)
    {
        // Si nous n'avons pas de conversation active, ignorer la mise à jour
        if (! $this->conversationId) {
            return;
        }

        // Mettre à jour depuis la base de données plutôt que d'utiliser la valeur passée
        $this->updateTokensFromDatabase();

        // Vérifier si nous devons générer un résumé automatiquement (seuil de 90%)
        if ($this->tokenLimit && $this->tokensUsed > 0) {
            if ($this->memoryService->shouldSummarize($this->conversationId, $this->tokensUsed, $this->tokenLimit)) {
                $this->summarizeConversation();
            }
        }
    }

    /**
     * Appelé quand un envoi de message commence
     */
    public function onMessageLoadingStarted()
    {
        $this->isMessageSending = true;
    }

    /**
     * Appelé quand un envoi de message se termine
     */
    public function onMessageLoadingEnded()
    {
        $this->isMessageSending = false;
    }

    /**
     * Génère un résumé de la conversation actuelle
     */
    public function summarizeConversation()
    {
        // Vérifier si une requête est déjà en cours
        if ($this->isSummarizing) {
            Log::info('Tentative de résumé ignorée car un résumé est déjà en cours', [
                'conversation_id' => $this->conversationId,
            ]);

            $this->dispatch('notify', [
                'type' => 'info',
                'message' => 'Un résumé est déjà en cours de génération, veuillez patienter.',
            ]);

            return;
        }

        // Vérifier si un envoi de message est en cours
        if ($this->isMessageSending) {
            Log::info('Tentative de résumé ignorée car un envoi de message est en cours', [
                'conversation_id' => $this->conversationId,
            ]);

            $this->dispatch('notify', [
                'type' => 'info',
                'message' => 'Un message est en cours d\'envoi, veuillez patienter avant de générer un résumé.',
            ]);

            return;
        }

        // Vérifier si une conversation est sélectionnée
        if (! $this->conversationId) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Aucune conversation sélectionnée pour le résumé',
            ]);

            return;
        }

        // Vérifier si un résumé vient d'être fait récemment
        $conversation = Conversation::find($this->conversationId);
        if ($conversation && $conversation->summary_flag) {
            Log::info('Tentative de résumé sur une conversation déjà résumée', [
                'conversation_id' => $this->conversationId,
                'summary_flag' => $conversation->summary_flag,
                'tokens' => $conversation->tokens,
            ]);

            // Vérifier s'il y a eu de nouveaux messages depuis le dernier résumé
            $hasNewMessages = Message::where('conversation_id', $this->conversationId)
                ->where('id', '>', $conversation->summary_message_id)
                ->exists();

            if (! $hasNewMessages) {
                $this->dispatch('notify', [
                    'type' => 'info',
                    'message' => 'La conversation a déjà été résumée et aucun nouveau message n\'a été ajouté depuis.',
                ]);

                return;
            }
        }

        // Activer l'indicateur de chargement
        $this->isSummarizing = true;
        // Informer les autres composants que le résumé commence
        $this->dispatch('summarizingStarted');

        Log::info('Début de la génération du résumé', [
            'conversation_id' => $this->conversationId,
            'isSummarizing' => $this->isSummarizing,
        ]);

        try {
            $success = $this->memoryService->updateSummary($this->conversationId);

            if ($success) {
                // Rafraîchir le compteur de tokens après la génération du résumé
                $this->updateTokensFromDatabase();

                $this->dispatch('notify', [
                    'type' => 'success',
                    'message' => 'Résumé de la conversation généré avec succès',
                ]);

                Log::info('Résumé généré avec succès', [
                    'conversation_id' => $this->conversationId,
                    'tokens_after_summary' => $this->tokensUsed,
                ]);
            } else {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'Erreur lors de la génération du résumé',
                ]);

                Log::error('Échec de la génération du résumé', [
                    'conversation_id' => $this->conversationId,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Erreur lors de la génération du résumé: '.$e->getMessage(), [
                'conversation_id' => $this->conversationId,
                'exception' => get_class($e),
            ]);

            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Erreur lors de la génération du résumé: '.$e->getMessage(),
            ]);
        } finally {
            // Désactiver l'indicateur de chargement, peu importe le résultat
            $this->isSummarizing = false;
            // Informer les autres composants que le résumé est terminé
            $this->dispatch('summarizingEnded');

            Log::info('Fin de la génération du résumé', [
                'conversation_id' => $this->conversationId,
                'isSummarizing' => $this->isSummarizing,
            ]);
        }
    }

    /**
     * Calcule le pourcentage de tokens utilisés
     */
    public function getTokenPercentageProperty()
    {
        if (! $this->tokenLimit || $this->tokenLimit <= 0) {
            return 0;
        }

        return min(100, round(($this->tokensUsed / $this->tokenLimit) * 100));
    }

    /**
     * Détermine la couleur de la barre de progression
     */
    public function getProgressColorProperty()
    {
        $percentage = $this->token_percentage;

        if ($percentage < 50) {
            return '#138f40'; // Vert pour moins de 50%
        } elseif ($percentage < 80) {
            return '#ab6413'; // Jaune pour entre 50% et 80%
        } else {
            return '#b01313'; // Rouge pour plus de 80%
        }
    }

    public function render()
    {
        return view('livewire.token-counter');
    }
}
