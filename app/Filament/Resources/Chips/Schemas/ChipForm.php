<?php

namespace App\Filament\Resources\Chips\Schemas;

use App\Models\Chip;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ChipForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('uid')
                    ->label('Chip-ID')
                    ->required()
                    ->regex('/^[0-9A-Fa-f:\-\s]+$/')
                    ->dehydrateStateUsing(fn (string $state): string => Chip::normalizeUid($state))
                    ->unique(ignoreRecord: true),
                Select::make('user_id')
                    ->label('Benutzer')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                TextInput::make('label')
                    ->label('Bezeichnung'),
                Toggle::make('is_active')
                    ->label('Aktiv')
                    ->default(true),
            ]);
    }
}
