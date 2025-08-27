<?php

namespace App\Filament\Resources\AIModelResource\Pages;

use App\Events\AIModelChanged;
use App\Filament\Resources\AIModelResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAIModel extends EditRecord
{
    protected static string $resource = AIModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->after(function () {
                    // Déclencher l'événement de suppression de modèle AI pour les mises à jour en temps réel
                    event(new AIModelChanged('deleted', $this->record));
                }),
        ];
    }

    protected function afterSave(): void
    {
        // Déclencher l'événement de mise à jour de modèle AI pour les mises à jour en temps réel
        event(new AIModelChanged('updated', $this->record));
    }
}
