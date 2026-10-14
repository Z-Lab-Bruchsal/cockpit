<?php

namespace App\Filament\Resources\Interests\Schemas;

use App\Models\Interest;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class InterestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(100)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state, ?Interest $record): void {
                        if ($record === null && blank($get('slug'))) {
                            $set('slug', Str::slug($state ?? '', language: 'de'));
                        }
                    }),
                TextInput::make('slug')
                    ->label('Kurzname')
                    ->required()
                    ->alphaDash()
                    ->maxLength(100)
                    ->unique(ignoreRecord: true),
                ColorPicker::make('color')
                    ->label('Farbe')
                    ->hex()
                    ->regex('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'),
                Toggle::make('is_active')
                    ->label('Aktiv')
                    ->helperText('Inaktive Interessen werden Eltern nicht mehr zur Auswahl angeboten.')
                    ->default(true),
            ]);
    }
}
