<?php

namespace App\Filament\Widgets;

use App\Models\ModelInstallation;
use Filament\Facades\Filament;
use Filament\Tables;
use Filament\Widgets\Concerns\CanPoll;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class ModelInstallationsWidget extends BaseWidget
{
    use CanPoll;

    protected static ?string $heading = 'Téléchargements de modèles';

    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        $userId = Filament::auth()?->id();
        return ModelInstallation::query()
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->latest();
    }

    protected function isTablePaginationEnabled(): bool
    {
        return true;
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('full_name')
                ->label('Modèle')
                ->searchable(),
            Tables\Columns\TextColumn::make('status')
                ->label('Statut')
                ->badge()
                ->colors([
                    'warning' => 'queued',
                    'info' => 'running',
                    'success' => 'succeeded',
                    'danger' => 'failed',
                ]),
            Tables\Columns\TextColumn::make('progress')
                ->label('Progression')
                ->formatStateUsing(function ($state, $record) {
                    $status = $record->status ?? null;
                    if (! in_array($status, ['running', 'succeeded'])) {
                        return '—';
                    }
                    $percent = (int) ($record->progress ?? 0);
                    $percent = max(0, min(100, $percent));
                    $bar = <<<HTML
                        <div class="w-40">
                            <div class="h-2 bg-gray-200 dark:bg-gray-700 rounded">
                                <div class="h-2 bg-primary-500 rounded" style="width: {$percent}%"></div>
                            </div>
                            <div class="text-xs mt-1">{$percent}%</div>
                        </div>
                    HTML;
                    return $bar;
                })
                ->html(),
            Tables\Columns\TextColumn::make('status_text')
                ->label('Détails')
                ->toggleable(),
            Tables\Columns\TextColumn::make('started_at')
                ->label('Début')
                ->dateTime()
                ->since(),
            Tables\Columns\TextColumn::make('finished_at')
                ->label('Fin')
                ->dateTime()
                ->since()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    protected function getTablePollingInterval(): ?string
    {
        return '2s';
    }
}
