<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AIModelResource\Pages;
use App\Models\AIModel;
use App\Models\ModelInstallation;
use App\Services\ModelSyncService;
use App\Jobs\InstallOllamaModel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class AIModelResource extends Resource
{
    protected static ?string $model = AIModel::class;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationLabel = 'Modèles LLM';

    protected static ?int $navigationSort = 5;

    // Disable the default "Create" button on the List page
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('full_name')
                    ->label('Nom complet (Ollama)')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('size')
                    ->label('Taille (bytes)')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false),
                Forms\Components\Toggle::make('is_active')
                    ->label('Actif')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Nom complet')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('size')
                    ->label('Taille')
                    ->formatStateUsing(function ($state) {
                        $bytes = (int) ($state ?? 0);
                        $gb = $bytes > 0 ? number_format($bytes / (1024 * 1024 * 1024), 2) . ' GB' : '-';
                        return $gb;
                    })
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_synced_at')
                    ->label('Dernière synchro')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
            ])
            // Make the table read-only: no per-row edit/delete actions
            ->actions([
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
                    ->label('Synchroniser depuis Ollama')
                    ->icon('heroicon-o-arrow-path')
                    ->form([
                        Forms\Components\Toggle::make('deactivate_missing')
                            ->label('Désactiver les modèles manquants')
                            ->default(false),
                    ])
                    ->action(function (array $data) {
                        $service = app(ModelSyncService::class);
                        $stats = $service->sync((bool) ($data['deactivate_missing'] ?? false));

                        Notification::make()
                            ->title('Synchronisation terminée')
                            ->body("Créés: {$stats['created']} — Mis à jour: {$stats['updated']} — Désactivés: {$stats['deactivated']} — Ignorés: {$stats['skipped']}")
                            ->success()
                            ->send();
                    }),
                Action::make('installOllamaModel')
                    ->label('Installer un modèle')
                    ->icon('heroicon-o-cloud-arrow-down')
                    ->modalHeading('Installer un modèle Ollama')
                    ->form([
                        Forms\Components\TextInput::make('full_name')
                            ->label('Nom complet du modèle (ex: llama3:8b)')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->action(function (array $data) {
                        $fullName = trim((string) ($data['full_name'] ?? ''));
                        if ($fullName === '') {
                            Notification::make()->title('Nom de modèle manquant')->danger()->send();
                            return;
                        }

                        $userId = auth()->id();
                        if (! $userId) {
                            Notification::make()->title('Utilisateur non authentifié')->danger()->send();
                            return;
                        }

                        // Pré-créer une ligne de suivi pour affichage immédiat dans le widget
                        $installation = ModelInstallation::create([
                            'user_id' => $userId,
                            'full_name' => $fullName,
                            'status' => 'queued',
                            'progress' => null,
                            'status_text' => 'En attente du worker…',
                        ]);

                        InstallOllamaModel::dispatch($fullName, $userId, $installation->id);

                        Notification::make()
                            ->title('Téléchargement en cours')
                            ->body($fullName)
                            ->info()
                            ->send();
                    })
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAIModels::route('/'),
            // We keep create/edit routes available for future admin usage, but the Create button is hidden and table is read-only
            'create' => Pages\CreateAIModel::route('/create'),
            'edit' => Pages\EditAIModel::route('/{record}/edit'),
        ];
    }
}
