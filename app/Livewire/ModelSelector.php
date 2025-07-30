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
     * Écoute les événements
     */
    protected $listeners = [
        'modelSelected' => 'updateSelectedModel',
    ];

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
                    // Ajouter le modèle à la liste avec les informations de base
                    $this->availableModels[] = [
                        'name' => $model['name'],
                        'size' => $model['size'] ?? 0,
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des modèles: '.$e->getMessage());
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

        // Indiquer que la sélection vient de la sidebar
        session(['model_selection_source' => 'sidebar']);

        // Émettre un événement pour informer les autres composants
        $this->dispatch('modelSelected', $modelName);
    }

    /**
     * Met à jour le modèle sélectionné
     */
    public function updateSelectedModel($modelName)
    {
        // Mettre à jour le modèle sélectionné
        $this->selectedModel = $modelName;
    }

    public function render()
    {
        return view('livewire.model-selector');
    }
}
