<?php

namespace App\Filament\Resources\Notes\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;

class NotesForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Name')
                    ->required(),
                RichEditor::make('content')
                    ->label('Notiz')
                    ->columnSpanFull(),
            ]);
    }
}
