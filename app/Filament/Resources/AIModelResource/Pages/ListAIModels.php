<?php

namespace App\Filament\Resources\AIModelResource\Pages;

use App\Filament\Resources\AIModelResource;
// use App\Filament\Widgets\ModelInstallationsWidget;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAIModels extends ListRecords
{
    protected static string $resource = AIModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // ModelInstallationsWidget::class,
        ];
    }
}
