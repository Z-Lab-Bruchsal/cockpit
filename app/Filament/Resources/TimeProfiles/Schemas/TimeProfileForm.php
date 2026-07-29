<?php

namespace App\Filament\Resources\TimeProfiles\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TimeProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Name')
                    ->required(),
                TextInput::make('weekly_hours')
                    ->label('Wochenstunden')
                    ->numeric()
                    ->step(0.25)
                    ->required(),
                TextInput::make('description')
                    ->label('Beschreibung'),
            ]);
    }
}
