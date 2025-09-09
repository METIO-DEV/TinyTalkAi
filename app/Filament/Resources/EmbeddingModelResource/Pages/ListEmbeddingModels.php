<?php

namespace App\Filament\Resources\EmbeddingModelResource\Pages;

use App\Filament\Resources\EmbeddingModelResource;
use Filament\Resources\Pages\ListRecords;

class ListEmbeddingModels extends ListRecords
{
    protected static string $resource = EmbeddingModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Pas d'actions de création
        ];
    }
}
