<?php

namespace App\Livewire;

use App\Services\RagService;
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
     * Instance du service RAG
     */
    protected RagService $ragService;

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
        // Initialiser le service RAG
        $this->ragService = new RagService;

        // Récupérer la liste des modèles disponibles directement via l'API Ollama
        $this->fetchAvailableModels();

        // Récupérer le modèle sélectionné depuis la session
        $this->selectedModel = session('selected_model', '');

        // Si aucun modèle n'est sélectionné et qu'il y a des modèles disponibles, sélectionner le premier
        if (empty($this->selectedModel) && ! empty($this->availableModels)) {
            $this->selectedModel = $this->availableModels[0]['name'];
            session(['selected_model' => $this->selectedModel]);

            // Émettre un événement pour informer les autres composants
            $this->dispatch('modelSelected', $this->selectedModel);
        }
    }

    /**
     * Récupère la liste des modèles disponibles via l'API Ollama
     */
    private function fetchAvailableModels()
    {
        try {
            // Utiliser RagService pour récupérer uniquement les modèles de génération
            $models = $this->ragService->getAvailableGenerationModels();

            // Traitement des modèles pour ajouter les informations nécessaires
            $this->availableModels = [];

            // Récupération des paramètres de configuration avec valeurs par défaut
            $ollamaHost = config('services.ollama.host', 'host.docker.internal');
            $ollamaPort = config('services.ollama.port', '11434');
            $ollamaUrl = 'http://'.$ollamaHost.':'.$ollamaPort.'/api/tags';

            // Requête HTTP pour obtenir les détails des modèles (taille, etc.)
            $response = Http::timeout(5)->get($ollamaUrl);

            if ($response->successful()) {
                $data = $response->json();
                $modelDetails = $data['models'] ?? [];

                // Créer un tableau associatif pour un accès facile aux détails
                $modelDetailsMap = [];
                foreach ($modelDetails as $model) {
                    $modelDetailsMap[$model['name']] = $model;
                }

                // Ajouter uniquement les modèles de génération avec leurs détails
                foreach ($models as $modelName) {
                    $details = $modelDetailsMap[$modelName] ?? [];
                    $this->availableModels[] = [
                        'name' => $modelName,
                        'size' => $details['size'] ?? 0,
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

        // Émettre un événement pour créer une nouvelle conversation
        $this->dispatch('newConversation');
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
