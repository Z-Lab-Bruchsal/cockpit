<?php

namespace App\Filament\Resources\Terminals\Schemas;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class TerminalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Name')
                    ->required(),
                TextInput::make('username')
                    ->label('Benutzername (Basic Auth)')
                    ->required()
                    ->alphaDash()
                    ->unique(ignoreRecord: true),
                TextInput::make('password')
                    ->label('Passwort')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->minLength(12)
                    ->helperText('Wird nur gehasht gespeichert. Vor dem Speichern kopieren und ins Terminal eintragen. Leer lassen, um das bisherige Passwort zu behalten.')
                    ->suffixAction(
                        Action::make('generatePassword')
                            ->label('Passwort erzeugen')
                            ->icon(Heroicon::OutlinedArrowPath)
                            ->action(fn (Set $set) => $set('password', Str::password(24, symbols: false))),
                    ),
                Toggle::make('is_active')
                    ->label('Aktiv')
                    ->default(true),
            ]);
    }
}
