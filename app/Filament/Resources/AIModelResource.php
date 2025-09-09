<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AIModelResource\Pages;
use App\Models\AIModel;
// use App\Models\ModelInstallation;
use App\Services\ModelSyncService;
// use App\Jobs\InstallOllamaModel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;

class AIModelResource extends Resource
{
    protected static ?string $model = AIModel::class;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    public static function getNavigationLabel(): string
    {
        return __('Modèles LLM');
    }

    protected static ?int $navigationSort = 5;

    // Disable the default "Create" button on the List page
    public static function canCreate(): bool
    {
        return false;
    }

    // Disable editing of models
    public static function canEdit($record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label(__('Name'))
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('full_name')
                    ->label(__('Full name (Ollama)'))
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('size')
                    ->label(__('Size (bytes)'))
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false),
                Forms\Components\Select::make('family')
                    ->label(__('Family'))
                    ->options([
                        'llm' => 'LLM',
                        'embedding' => 'Embedding',
                    ])
                    ->default('llm')
                    ->disabled()
                    ->dehydrated(false),
                Forms\Components\Toggle::make('is_active')
                    ->label(__('Active'))
                    ->default(true),
                Forms\Components\Select::make('groups')
                    ->relationship(
                        'groups',
                        'name',
                        modifyQueryUsing: fn ($query) => $query->select(['groups.id', 'groups.name'])->orderBy('groups.name')
                    )
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->visible(fn ($record) => $record && $record->family === 'llm')
                    ->saveRelationshipsUsing(function ($record, $state) {
                        $record->syncGroups($state ? array_values((array) $state) : []);
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('full_name')
                    ->label(__('Full name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('size')
                    ->label(__('Size'))
                    ->formatStateUsing(function ($state) {
                        $bytes = (int) ($state ?? 0);
                        $gb = $bytes > 0 ? number_format($bytes / (1024 * 1024 * 1024), 2).' GB' : '-';

                        return $gb;
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('family')
                    ->label(__('Family'))
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'llm' => 'LLM',
                        'embedding' => 'Embedding',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'llm' => 'success',
                        'embedding' => 'info',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('Active'))
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('groups.name')
                    ->label(__('Groups'))
                    ->badge()
                    ->color('success')
                    ->searchable(),
                Tables\Columns\TextColumn::make('last_synced_at')
                    ->label(__('Last sync'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('family')
                    ->label(__('Family'))
                    ->options([
                        'llm' => 'LLM',
                        'embedding' => 'Embedding',
                    ]),
            ])
            // Make the table read-only: no per-row edit/delete actions
            ->actions([
                // Tables\Actions\EditAction::make(),
            ])
            // No bulk delete
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                ]),
            ])
            // Auto-refresh the table so new installs appear without manual sync
            ->poll('2s')
            ->headerActions([
                Action::make('syncFromOllama')
                    ->label(__('Sync from Ollama'))
                    ->icon('heroicon-o-arrow-path')
                    ->form([
                        Forms\Components\Toggle::make('deactivate_missing')
                            ->label(__('Deactivate missing models'))
                            ->default(false),
                    ])
                    ->action(function (array $data) {
                        $service = app(ModelSyncService::class);
                        $stats = $service->sync((bool) ($data['deactivate_missing'] ?? false));

                        Notification::make()
                            ->title(__('Sync completed'))
                            ->body(__('Sync stats', [
                                'created' => $stats['created'],
                                'updated' => $stats['updated'],
                                'deactivated' => $stats['deactivated'],
                                'skipped' => $stats['skipped'],
                            ]))
                            ->success()
                            ->send();
                    }),
                // Action::make('installOllamaModel')
                //     ->label(__('Install Ollama model'))
                //     ->icon('heroicon-o-cloud-arrow-down')
                //     ->modalHeading(__('Install Ollama model'))
                //     ->form([
                //         Forms\Components\TextInput::make('full_name')
                //             ->label(__('Full name of the model (ex: llama3:8b)'))
                //             ->required()
                //             ->maxLength(255),
                //     ])
                //     ->action(function (array $data) {
                //     $fullName = trim((string) ($data['full_name'] ?? ''));
                //     if ($fullName === '') {
                //         Notification::make()->title(__('Model name missing'))->danger()->send();
                //         return;
                //     }

                //     $userId = auth()->id();
                //     if (! $userId) {
                //         Notification::make()->title(__('User not authenticated'))->danger()->send();
                //         return;
                //     }

                //     // Pré-créer une ligne de suivi pour affichage immédiat dans le widget
                //     $installation = ModelInstallation::create([
                //         'user_id' => $userId,
                //         'full_name' => $fullName,
                //         'status' => 'queued',
                //         'progress' => null,
                //         'status_text' => 'En attente du worker…',
                //     ]);

                //     InstallOllamaModel::dispatch($fullName, $userId, $installation->id);

                //     Notification::make()
                //         ->title(__('Downloading in progress'))
                //         ->body($fullName)
                //         ->info()
                //         ->send();
                // })
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAIModels::route('/'),
        ];
    }
}
