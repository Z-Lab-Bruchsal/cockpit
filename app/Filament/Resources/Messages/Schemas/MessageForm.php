<?php

namespace App\Filament\Resources\Messages\Schemas;

use App\Filament\Forms\RecipientSelect;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MessageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                RecipientSelect::make('recipients')
                    ->label('An')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('subject')
                    ->label('Betreff')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                RichEditor::make('body')
                    ->label('Nachricht')
                    ->columnSpanFull(),
            ]);
    }
}
