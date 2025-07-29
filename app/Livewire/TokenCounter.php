<?php

namespace App\Livewire;

use App\Models\Conversation;
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
     * Écoute les événements de mise à jour du modèle et des tokens
     */
    protected $listeners = [
        'modelSelected' => 'updateSelectedModel',
        'tokensUpdated' => 'updateTokensUsed',
        'conversationSelected' => 'loadConversation',
        'conversationCleared' => 'clearConversation',
        'loadingComplete' => '$refresh',
    ];

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

                // Vérifier dans parameters.num_ctx (priorité 1)
                if (isset($modelDetails['parameters']) && isset($modelDetails['parameters']['num_ctx'])) {
                    $this->tokenLimit = (int) $modelDetails['parameters']['num_ctx'];
                    Log::info("Limite de tokens trouvée dans parameters.num_ctx: {$this->tokenLimit}");
                }
                // Recherche récursive de tout champ contenant "context" dans model_info (priorité 2)
                elseif (isset($modelDetails['model_info'])) {
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
                (stripos($key, 'context_length') !== false ||
                 stripos($key, 'context') !== false ||
                 stripos($key, 'ctx') !== false ||
                 stripos($key, 'length') !== false)) {

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
            return 'bg-custom-black'; // Noir pour moins de 50%
        } elseif ($percentage < 80) {
            return 'bg-gray-500'; // Gris pour entre 50% et 80%
        } else {
            return 'bg-red-500'; // Rouge pour plus de 80%
        }
    }

    public function render()
    {
        return view('livewire.token-counter');
    }
}
