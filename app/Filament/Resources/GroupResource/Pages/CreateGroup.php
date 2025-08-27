<?php

namespace App\Filament\Resources\GroupResource\Pages;

use App\Filament\Resources\GroupResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGroup extends CreateRecord
{
    protected static string $resource = GroupResource::class;

    protected function afterCreate(): void
    {
        // Récupérer le groupe nouvellement créé
        $group = $this->record;
        $collectionIds = $group->collections->pluck('id')->toArray();

        // Journaliser les collections associées pour débogage
        \Illuminate\Support\Facades\Log::info('Création d\'un groupe avec collections via Filament', [
            'group_id' => $group->id,
            'collections' => $collectionIds,
        ]);

        // Déclencher les événements pour chaque collection associée
        foreach ($collectionIds as $collectionId) {
            event(new \App\Events\CollectionGroupChanged('attached', $collectionId, [$group->id]));
        }
    }
}
