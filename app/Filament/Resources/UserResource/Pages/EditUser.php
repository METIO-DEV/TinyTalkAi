<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Actions;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Hash;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('changePassword')
                ->label(__('Change password'))
                ->icon('heroicon-o-key')
                ->color('warning')
                ->modalHeading(__('Change password'))
                ->form([
                    TextInput::make('new_password')
                        ->label(__('New password'))
                        ->password()
                        ->revealable()
                        ->autocomplete('new-password')
                        ->required()
                        ->rule('confirmed')
                        ->minLength(8),
                    TextInput::make('new_password_confirmation')
                        ->label(__('Confirm password'))
                        ->password()
                        ->revealable()
                        ->autocomplete('new-password')
                        ->required(),
                ])
                ->action(function (User $record, array $data) {
                    $record->password = Hash::make($data['new_password']);
                    $record->save();

                    Notification::make()
                        ->title(__('Password updated successfully'))
                        ->success()
                        ->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
