<?php

namespace App\Filament\Resources\CollectionResource\Pages;

use App\Events\CollectionChanged;
use App\Events\CollectionGroupChanged;
use App\Filament\Resources\CollectionResource;
use App\Services\QdrantCollectionsService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;

class EditCollection extends EditRecord
{
    protected static string $resource = CollectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->after(function () {
                    // Récupérer le nom de la collection supprimée
                    $collectionName = $this->record->name;

                    // Supprimer la collection dans Qdrant
                    $qdrantService = app(QdrantCollectionsService::class);
                    $result = $qdrantService->deleteCollection($collectionName);

                    if ($result) {
                        Log::info('Collection supprimée avec succès dans Qdrant', [
                            'collection' => $collectionName,
                        ]);

                        Notification::make()
                            ->title(__('Collection deleted'))
                            ->body(__('The collection was successfully deleted in Qdrant.'))
                            ->success()
                            ->send();
                    } else {
                        Log::error('Échec de la suppression de la collection dans Qdrant', [
                            'collection' => $collectionName,
                        ]);

                        Notification::make()
                            ->title(__('Warning'))
                            ->body(__('The collection was deleted in the database but not in Qdrant.'))
                            ->warning()
                            ->send();
                    }

                    // Déclencher l'événement de suppression de collection pour les mises à jour en temps réel
                    event(new CollectionChanged('deleted', $this->record));
                }),
        ];
    }

    protected function afterSave(): void
    {
        // Vérifier si le nom de la collection a été modifié
        if ($this->record->wasChanged('name')) {
            // Dans ce cas, il faudrait supprimer l'ancienne collection et en créer une nouvelle
            // car Qdrant ne permet pas de renommer une collection
            $oldName = $this->record->getOriginal('name');
            $newName = $this->record->name;

            $qdrantService = app(QdrantCollectionsService::class);

            // Supprimer l'ancienne collection
            $deleted = $qdrantService->deleteCollection($oldName);

            // Créer la nouvelle collection
            $created = $qdrantService->createCollection($newName);

            if ($deleted && $created) {
                Log::info('Collection renommée avec succès dans Qdrant', [
                    'old_name' => $oldName,
                    'new_name' => $newName,
                ]);

                Notification::make()
                    ->title(__('Collection renamed'))
                    ->body(__('The collection was successfully renamed in Qdrant.'))
                    ->success()
                    ->send();
            } else {
                Log::error('Échec du renommage de la collection dans Qdrant', [
                    'old_name' => $oldName,
                    'new_name' => $newName,
                    'deleted' => $deleted,
                    'created' => $created,
                ]);

                Notification::make()
                    ->title(__('Warning'))
                    ->body(__('The collection was renamed in the database but not in Qdrant.'))
                    ->warning()
                    ->send();
            }
        }

        // Pour les autres modifications (description, is_active, etc.)
        // Qdrant ne stocke pas ces informations, donc aucune action n'est nécessaire

        // Déclencher l'événement de mise à jour de collection pour les mises à jour en temps réel
        event(new CollectionChanged('updated', $this->record));
    }

    // Intercepter les changements de relations many-to-many pour les groupes
    protected function afterSyncRelations(): void
    {
        parent::afterSyncRelations();

        // Vérifier si la relation avec les groupes a été modifiée
        if ($this->record->wasRelationshipSynced('groups')) {
            $groupIds = $this->record->groups->pluck('id')->toArray();

            // Déclencher l'événement de changement de relation collection-groupe
            event(new CollectionGroupChanged('updated', $this->record->id, $groupIds));
        }
    }
}
