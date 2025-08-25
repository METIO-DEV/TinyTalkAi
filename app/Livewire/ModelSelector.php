<?php

namespace App\Livewire;

use App\Models\AIModel;
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
        // Charger les modèles depuis la base de données (gérés en administration)
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
     * Récupère la liste des modèles disponibles via la base de données (Filament admin)
     */
    private function fetchAvailableModels()
    {
        try {
            $models = AIModel::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['full_name', 'size']);

            $embeddingModel = config('services.ollama.embedding_model', 'nomic-embed-text');

            $this->availableModels = $models->filter(function ($m) use ($embeddingModel) {
                $full = strtolower($m->full_name ?? '');
                // Exclure le modèle d'embedding configuré et tout modèle contenant 'embed'
                if ($full === strtolower($embeddingModel)) {
                    return false;
                }
                if (str_contains($full, 'embed')) {
                    return false;
                }
                // Exclure aussi la variante potentiellement mal orthographiée fournie par l'utilisateur
                if (str_contains($full, 'bomic-embed')) {
                    return false;
                }
                return true;
            })->map(function ($m) {
                return [
                    // Le sélecteur attend 'name' comme identifiant complet utilisable par l'API Ollama
                    'name' => $m->full_name,
                    'size' => (int) ($m->size ?? 0),
                ];
            })->values()->toArray();
        } catch (\Exception $e) {
            Log::error('Erreur lors du chargement des modèles depuis la BD: ' . $e->getMessage());
            $this->availableModels = [];
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
