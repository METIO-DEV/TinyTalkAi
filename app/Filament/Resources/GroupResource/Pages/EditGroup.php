<?php

namespace App\Filament\Resources\GroupResource\Pages;

use App\Filament\Resources\GroupResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EditGroup extends EditRecord
{
    protected static string $resource = GroupResource::class;

    // Variable pour stocker les anciennes collections
    protected array $oldCollectionIds = [];

    // Variable pour stocker les anciens modèles
    protected array $oldModelIds = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    // Méthode appelée au chargement de la page
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Récupérer le groupe
        $group = $this->record;

        // Stocker les anciennes collections au chargement initial de la page
        $this->oldCollectionIds = $group->collections->pluck('id')->toArray();
        // Stocker les anciens modèles au chargement initial de la page
        $this->oldModelIds = $group->models->pluck('id')->toArray();

        // Log pour débogage
        Log::info('Collections et modèles au chargement initial de la page', [
            'group_id' => $group->id,
            'old_collections' => $this->oldCollectionIds,
            'old_models' => $this->oldModelIds,
        ]);

        return $data;
    }

    // Méthode appelée avant la sauvegarde
    protected function beforeSave(): void
    {
        // Récupérer le groupe
        $group = $this->record;

        // Log des données du formulaire
        Log::info('Données du formulaire avant sauvegarde', [
            'group_id' => $group->id,
            'form_data' => $this->data,
            'collections_data' => $this->data['collections'] ?? [],
            'models_data' => $this->data['models'] ?? [],
        ]);

        // Vérifier directement dans la base de données
        $pivotCollections = DB::table('collection_group')
            ->where('group_id', $group->id)
            ->get();
        $pivotModels = DB::table('model_group')
            ->where('group_id', $group->id)
            ->get();

        Log::info('Données des tables pivot avant sauvegarde', [
            'group_id' => $group->id,
            'collections_pivot' => $pivotCollections->toArray(),
            'models_pivot' => $pivotModels->toArray(),
        ]);
    }

    protected function afterSave(): void
    {
        // Récupérer le groupe
        $group = $this->record;

        // Forcer le rechargement des relations
        $group->load(['collections', 'models']);

        // Récupérer les valeurs actuelles après sauvegarde
        $newCollectionIds = $group->collections->pluck('id')->toArray();
        $newModelIds = $group->models->pluck('id')->toArray();

        // Log des données du formulaire après sauvegarde
        Log::info('Données du formulaire après sauvegarde', [
            'group_id' => $group->id,
            'form_data' => $this->data,
            'collections_data' => $this->data['collections'] ?? [],
            'models_data' => $this->data['models'] ?? [],
        ]);

        // Vérifier directement dans la base de données après sauvegarde
        $pivotCollections = DB::table('collection_group')
            ->where('group_id', $group->id)
            ->get();
        $pivotModels = DB::table('model_group')
            ->where('group_id', $group->id)
            ->get();

        Log::info('Données des tables pivot après sauvegarde', [
            'group_id' => $group->id,
            'collections_pivot' => $pivotCollections->toArray(),
            'models_pivot' => $pivotModels->toArray(),
        ]);

        // Identifier les collections ajoutées et supprimées
        $formCollectionIds = $this->data['collections'] ?? [];
        $addedCollectionIds = array_diff($formCollectionIds, $this->oldCollectionIds);
        $removedCollectionIds = array_diff($this->oldCollectionIds, $formCollectionIds);

        // Journaliser les modifications pour débogage (collections)
        Log::info('Modification des collections d\'un groupe via Filament', [
            'group_id' => $group->id,
            'old_collections' => $this->oldCollectionIds,
            'new_collections_from_form' => $formCollectionIds,
            'new_collections_from_db' => $newCollectionIds,
            'added' => $addedCollectionIds,
            'removed' => $removedCollectionIds,
        ]);

        // Déclencher les événements pour les collections ajoutées
        foreach ($addedCollectionIds as $collectionId) {
            event(new \App\Events\CollectionGroupChanged('attached', $collectionId, [$group->id]));
            Log::info('Événement CollectionGroupChanged déclenché pour collection ajoutée', [
                'action' => 'attached',
                'collection_id' => $collectionId,
                'group_id' => $group->id,
            ]);
        }

        // Déclencher les événements pour les collections supprimées
        foreach ($removedCollectionIds as $collectionId) {
            event(new \App\Events\CollectionGroupChanged('detached', $collectionId, [$group->id]));
            Log::info('Événement CollectionGroupChanged déclenché pour collection supprimée', [
                'action' => 'detached',
                'collection_id' => $collectionId,
                'group_id' => $group->id,
            ]);
        }

        // ============================
        // Gestion des MODÈLES (models)
        // ============================

        // Identifier les modèles ajoutés et supprimés en comparant avec les données du formulaire
        $formModelIds = $this->data['models'] ?? [];
        $addedModelIds = array_diff($formModelIds, $this->oldModelIds);
        $removedModelIds = array_diff($this->oldModelIds, $formModelIds);

        // Journaliser les modifications pour débogage (models)
        Log::info('Modification des modèles d\'un groupe via Filament', [
            'group_id' => $group->id,
            'old_models' => $this->oldModelIds,
            'new_models_from_form' => $formModelIds,
            'new_models_from_db' => $newModelIds,
            'added' => $addedModelIds,
            'removed' => $removedModelIds,
        ]);

        // Déclencher les événements pour les modèles ajoutés
        foreach ($addedModelIds as $modelId) {
            event(new \App\Events\ModelGroupChanged('attached', (int) $modelId, [$group->id]));
            Log::info('Événement ModelGroupChanged déclenché pour modèle ajouté', [
                'action' => 'attached',
                'model_id' => (int) $modelId,
                'group_id' => $group->id,
            ]);
        }

        // Déclencher les événements pour les modèles supprimés
        foreach ($removedModelIds as $modelId) {
            event(new \App\Events\ModelGroupChanged('detached', (int) $modelId, [$group->id]));
            Log::info('Événement ModelGroupChanged déclenché pour modèle supprimé', [
                'action' => 'detached',
                'model_id' => (int) $modelId,
                'group_id' => $group->id,
            ]);
        }
    }
}
