<?php

namespace App\Filament\Resources\Kids\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class KidForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
            ]);
    }
}
