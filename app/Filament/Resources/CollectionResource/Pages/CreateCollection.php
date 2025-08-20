<?php

namespace App\Filament\Resources\CollectionResource\Pages;

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
                ->title('Collection créée')
                ->body('La collection a été créée avec succès dans Qdrant.')
                ->success()
                ->send();
        } else {
            Log::error('Échec de la création de la collection dans Qdrant', [
                'collection' => $collection->name,
            ]);

            Notification::make()
                ->title('Attention')
                ->body('La collection a été créée en base de données mais pas dans Qdrant. Veuillez vérifier la connexion à Qdrant.')
                ->warning()
                ->send();
        }
    }
}
