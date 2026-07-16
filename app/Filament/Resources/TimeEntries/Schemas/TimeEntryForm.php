<?php

namespace App\Filament\Resources\TimeEntries\Schemas;

use App\Enums\TimeEntryType;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TimeEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('Benutzer')
                    ->options(fn () => User::query()
                        ->whereIn('id', filament()->auth()->user()->visibleUserIds())
                        ->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                Select::make('type')
                    ->label('Art')
                    ->options(TimeEntryType::class)
                    ->required(),
                DateTimePicker::make('happened_at')
                    ->label('Zeitpunkt')
                    ->seconds(false)
                    ->required(),
                TextInput::make('note')
                    ->label('Notiz'),
            ]);
    }
}
