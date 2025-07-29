<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class ModelSelector extends Component
{
    /**
     * Le modèle actuellement sélectionné
     */
    public string $selectedModel = '';

    /**
     * Liste des modèles disponibles
     */
    public array $availableModels = [];

    /**
     * Initialisation du composant
     */
    public function mount()
    {
        // Récupérer le modèle sélectionné depuis la session
        $this->selectedModel = session('selected_model', '');

        // Récupérer la liste des modèles disponibles directement via l'API Ollama
        $this->fetchAvailableModels();
    }

    /**
     * Récupère la liste des modèles disponibles via l'API Ollama
     */
    private function fetchAvailableModels()
    {
        try {
            // Récupération des paramètres de configuration avec valeurs par défaut
            $ollamaHost = config('services.ollama.host', 'host.docker.internal');
            $ollamaPort = config('services.ollama.port', '11434');
            $ollamaUrl = 'http://'.$ollamaHost.':'.$ollamaPort.'/api/tags';

            // Requête HTTP avec timeout court pour éviter de bloquer l'interface utilisateur
            $response = Http::timeout(5)->get($ollamaUrl);

            // Vérification du succès de la requête (code 2xx)
            if ($response->successful()) {
                $data = $response->json();
                $models = $data['models'] ?? [];

                // Traitement des modèles pour ajouter les informations nécessaires
                $this->availableModels = [];
                foreach ($models as $model) {
                    // Récupérer les détails du modèle pour obtenir la limite de tokens
                    $modelDetails = $this->getModelDetails($model['name']);

                    // Ajouter le modèle à la liste avec toutes les informations nécessaires
                    $this->availableModels[] = [
                        'name' => $model['name'],
                        'size' => $model['size'] ?? 0,
                        'token_limit' => $modelDetails['token_limit'] ?? null,
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des modèles: '.$e->getMessage());
        }
    }

    /**
     * Récupère les détails d'un modèle spécifique via l'API Ollama
     */
    private function getModelDetails($modelName)
    {
        try {
            // Récupération des paramètres de configuration avec valeurs par défaut
            $ollamaHost = config('services.ollama.host', 'host.docker.internal');
            $ollamaPort = config('services.ollama.port', '11434');
            $ollamaUrl = 'http://'.$ollamaHost.':'.$ollamaPort.'/api/show';

            // Requête HTTP avec timeout court pour éviter de bloquer l'interface utilisateur
            $response = Http::timeout(3)->post($ollamaUrl, [
                'name' => $modelName,
            ]);

            // Vérification du succès de la requête (code 2xx)
            if ($response->successful()) {
                $modelDetails = $response->json();

                // Extraire la limite de tokens si disponible
                $tokenLimit = null;

                // Vérifier dans parameters.num_ctx
                if (isset($modelDetails['parameters']) && isset($modelDetails['parameters']['num_ctx'])) {
                    $tokenLimit = (int) $modelDetails['parameters']['num_ctx'];
                }
                // Vérifier aussi dans model_info.llama.context_length si disponible
                elseif (isset($modelDetails['model_info']) && isset($modelDetails['model_info']['llama.context_length'])) {
                    $tokenLimit = (int) $modelDetails['model_info']['llama.context_length'];
                }

                return [
                    'success' => true,
                    'details' => $modelDetails,
                    'token_limit' => $tokenLimit,
                ];
            }

            return ['success' => false, 'error' => 'Échec de la requête'];
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des détails du modèle: '.$e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Sélectionne un modèle et émet un événement pour informer les autres composants
     */
    public function selectModel(string $modelName)
    {
        $this->selectedModel = $modelName;

        // Sauvegarder le modèle sélectionné dans la session
        session(['selected_model' => $modelName]);

        // Trouver la limite de tokens pour ce modèle
        $tokenLimit = null;
        foreach ($this->availableModels as $model) {
            if ($model['name'] === $modelName) {
                $tokenLimit = $model['token_limit'];
                break;
            }
        }

        // Émettre un événement pour informer les autres composants
        $this->dispatch('modelSelected', $modelName);
    }

    public function render()
    {
        return view('livewire.model-selector');
    }
}
