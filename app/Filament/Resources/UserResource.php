<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    public static function getNavigationLabel(): string
    {
        return __('Users');
    }

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informations utilisateur')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('Name'))
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->label(__('Email'))
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('password')
                            ->label(__('Password'))
                            ->password()
                            ->autocomplete('new-password')
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->hidden(fn (string $operation): bool => $operation === 'edit'),
                    ])->columns(2),
                Forms\Components\Section::make('Roles and groups')
                    ->schema([
                        Forms\Components\Select::make('roles')
                            ->label(__('Roles'))
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload(),
                        Forms\Components\Select::make('groups')
                            ->label(__('Groups'))
                            ->relationship(
                                'groups',
                                'name',
                                modifyQueryUsing: fn ($query) => $query->select(['groups.id', 'groups.name'])->orderBy('groups.name')
                            )
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->saveRelationshipsUsing(function ($record, $state) {
                                $record->syncGroups($state ? array_values((array) $state) : []);
                            }),
                    ])->columns(2),
                Forms\Components\Section::make(__(' Owned collections'))
                    ->schema([
                        Forms\Components\Placeholder::make('owned_collections_list')
                            ->label(__('Owned collections'))
                            ->content(function (?User $record) {
                                if (! $record) {
                                    return '—';
                                }
                                $names = $record->collections()->pluck('name')->toArray();

                                return empty($names) ? '—' : implode(', ', $names);
                            }),
                    ])
                    ->columns(1)
                    ->hidden(fn (string $operation): bool => $operation === 'create'),
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
                Tables\Columns\TextColumn::make('email')
                    ->label(__('Email'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label(__('Roles'))
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('groups.name')
                    ->label(__('Groups'))
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('collections.name')
                    ->label(__('Owned collections'))
                    ->badge()
                    ->color('warning')
                    ->limit(3)
                    ->tooltip(fn ($record) => $record->collections->pluck('name')->join(', ')),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('Updated at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload(),
                Tables\Filters\SelectFilter::make('groups')
                    ->relationship('groups', 'name')
                    ->multiple()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
