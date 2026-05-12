<?php

namespace App\Filament\Resources\Kids\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class KidInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('name'),
                TextEntry::make('email')
                    ->label('Email address')
                    ->placeholder('-'),
            ]);
    }
}
