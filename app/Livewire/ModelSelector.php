<?php

namespace App\Livewire;

use App\Models\AIModel;
use App\Services\ModelSyncService;
use Illuminate\Support\Facades\Auth;
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
        'refreshModels' => 'fetchAvailableModels',
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

        // Si un modèle est sélectionné mais qu'aucun modèle n'est disponible, réinitialiser la sélection
        if (! empty($this->selectedModel) && empty($this->availableModels)) {
            $this->selectedModel = '';
            session(['selected_model' => '']);

            // Émettre un événement pour informer les autres composants
            $this->dispatch('modelSelected', $this->selectedModel);

            // Notifier l'utilisateur si connecté
            if (Auth::check()) {
                $this->dispatch('showNotification', [
                    'type' => 'warning',
                    'message' => 'Vous n\'avez accès à aucun modèle. Veuillez contacter un administrateur.',
                ]);
            }
        }
    }

    /**
     * Récupère la liste des modèles disponibles via la base de données (Filament admin)
     * et filtre selon les groupes de l'utilisateur
     */
    public function fetchAvailableModels()
    {
        try {
            $user = Auth::user();
            $userGroups = $user ? $user->groups->pluck('id')->toArray() : [];
            $isAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole('admin');

            if (! AIModel::query()->where('is_active', true)->exists()) {
                $stats = app(ModelSyncService::class)->sync();

                Log::info('Synchronisation Ollama de secours au chargement des modèles', [
                    'created' => $stats['created'],
                    'updated' => $stats['updated'],
                    'deactivated' => $stats['deactivated'],
                    'skipped' => $stats['skipped'],
                    'errors' => $stats['errors'],
                ]);
            }

            // Si l'utilisateur est connecté mais n'a pas de groupes, retourner une liste vide
            if ($user && ! $isAdmin && empty($userGroups)) {
                $this->availableModels = [];

                // Log pour débogage
                Log::debug('Aucun modèle disponible car l\'utilisateur n\'appartient à aucun groupe', [
                    'user_id' => $user->id,
                ]);

                // Revalider la sélection
                $this->resetSelectionAfterRefresh();

                return;
            }

            // Requête de base pour les modèles actifs
            $query = AIModel::query()->where('is_active', true);

            // Les admins voient tous les modèles actifs, les autres sont filtrés par groupes
            if ($user && ! $isAdmin && ! empty($userGroups)) {
                // Récupérer les modèles associés aux groupes de l'utilisateur
                $query->whereHas('groups', function ($q) use ($userGroups) {
                    $q->whereIn('groups.id', $userGroups);
                });
            }

            $models = $query->orderBy('name')->get(['full_name', 'size']);

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

            // Log pour débogage
            Log::debug('Modèles disponibles pour l\'utilisateur', [
                'user_id' => $user ? $user->id : 'non connecté',
                'user_groups' => $userGroups,
                'models_count' => count($this->availableModels),
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors du chargement des modèles depuis la BD: '.$e->getMessage());
            $this->availableModels = [];
        }

        // Revalider la sélection après rafraîchissement
        $this->resetSelectionAfterRefresh();
    }

    /**
     * Ajuste selectedModel pour rester cohérent avec la liste actualisée
     */
    private function resetSelectionAfterRefresh(): void
    {
        $names = array_map(fn ($m) => $m['name'], $this->availableModels);

        // Si le modèle sélectionné actuel n'est plus disponible
        if (! empty($this->selectedModel) && ! in_array($this->selectedModel, $names, true)) {
            if (! empty($this->availableModels)) {
                // Sélectionner le premier modèle disponible
                $this->selectedModel = $this->availableModels[0]['name'];
                session(['selected_model' => $this->selectedModel]);
                $this->dispatch('modelSelected', $this->selectedModel);
            } else {
                // Aucune option : vider la sélection
                $this->selectedModel = '';
                session(['selected_model' => '']);
                $this->dispatch('modelSelected', $this->selectedModel);
            }

            return;
        }

        // Si aucun modèle n'est sélectionné mais des modèles existent, sélectionner le premier
        if (empty($this->selectedModel) && ! empty($this->availableModels)) {
            $this->selectedModel = $this->availableModels[0]['name'];
            session(['selected_model' => $this->selectedModel]);
            $this->dispatch('modelSelected', $this->selectedModel);
        }

        // Si aucun modèle disponible, s'assurer que la session est vide
        if (empty($this->availableModels)) {
            session(['selected_model' => '']);
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
