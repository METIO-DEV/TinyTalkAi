<?php

namespace App\Filament\Resources\CollectionResource\Pages;

use App\Events\CollectionChanged;
use App\Filament\Resources\CollectionResource;
use App\Services\QdrantCollectionsService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;

class CreateCollection extends CreateRecord
{
    protected static string $resource = CollectionResource::class;

    protected function afterCreate(): void
    {
        // Récupérer la collection qui vient d'être créée
        $collection = $this->record;

        // Créer la collection dans Qdrant
        $qdrantService = app(QdrantCollectionsService::class);
        $result = $qdrantService->createCollection($collection->name);

        if ($result) {
            Log::info('Collection créée avec succès dans Qdrant', [
                'collection' => $collection->name,
            ]);

            Notification::make()
                ->title(__('Collection created'))
                ->body(__('The collection was successfully created in Qdrant.'))
                ->success()
                ->send();
        } else {
            Log::error('Échec de la création de la collection dans Qdrant', [
                'collection' => $collection->name,
            ]);

            Notification::make()
                ->title(__('Warning'))
                ->body(__('The collection was created in the database but not in Qdrant. Please check the Qdrant connection.'))
                ->warning()
                ->send();
        }

        // Déclencher l'événement de création de collection pour les mises à jour en temps réel
        event(new CollectionChanged('created', $collection));
    }
}
