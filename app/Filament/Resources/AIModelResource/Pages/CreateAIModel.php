<?php

namespace App\Filament\Resources\AIModelResource\Pages;

use App\Events\AIModelChanged;
use App\Filament\Resources\AIModelResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAIModel extends CreateRecord
{
    protected static string $resource = AIModelResource::class;

    protected function afterCreate(): void
    {
        // Déclencher l'événement de création de modèle AI pour les mises à jour en temps réel
        event(new AIModelChanged('created', $this->record));
    }
}
