<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmbeddingModelResource\Pages;
use App\Models\AIModel;
use App\Models\EmbeddingModel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EmbeddingModelResource extends Resource
{
    protected static ?string $model = AIModel::class;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationLabel = 'Modèles d\'embedding';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('active_embedding_model')
                    ->label('Modèle d\'embedding actif')
                    ->options(
                        AIModel::where('family', 'embedding')
                            ->where('is_active', true)
                            ->pluck('name', 'full_name')
                            ->toArray()
                    )
                    ->required()
                    ->searchable()
                    ->helperText('Sélectionnez le modèle d\'embedding à utiliser pour les recherches sémantiques')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(AIModel::where('family', 'embedding')->where('is_active', true))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Nom complet')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_current')
                    ->label('Actif')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->full_name === EmbeddingModel::getActiveModel())
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),
                Tables\Columns\TextColumn::make('size_gb')
                    ->label('Taille (GB)')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Mis à jour')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('activate')
                    ->label('Activer')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->full_name !== EmbeddingModel::getActiveModel())
                    ->action(function ($record) {
                        EmbeddingModel::setActiveModel($record->full_name);
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Modèle d\'embedding activé')
                            ->body("Le modèle {$record->name} est maintenant actif")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmbeddingModels::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
